
<?php
require_once __DIR__ . '/includes/init.php';

date_default_timezone_set('Asia/Manila');

$PAGE_TITLE = 'Quiz Results';
$PAGE_ICON = 'bars';

// Get quiz ID
$quizId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($quizId <= 0) {
    header('Location: quiz1.php?tab=pub');
    exit;
}

// Get quiz information
$stmt = $pdo->prepare("
    SELECT *
    FROM quiz
    WHERE quizId = ?
    LIMIT 1
");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: quiz1.php?tab=pub');
    exit;
}

// Get all attempts for this quiz
$stmt = $pdo->prepare("
    SELECT
        g.gameId,
        g.userId,
        g.score,
        g.totalPoints,
        g.startedAt,
        g.endedAt,
        s.studFirstName,
        s.studMiddleName,
        s.studLastName,
        s.studExtName,
        s.blkName
    FROM gamequiz g
    INNER JOIN students s ON s.studId = g.userId
    WHERE g.quizId = ?
    ORDER BY
        s.studLastName ASC,
        s.studFirstName ASC,
        CASE
            WHEN g.totalPoints > 0
            THEN g.score / g.totalPoints
            ELSE 0
        END DESC,
        g.endedAt DESC,
        g.gameId DESC
");

$stmt->execute([$quizId]);
$attemptRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group attempts by student
$students = [];

foreach ($attemptRows as $attempt) {
    $userId = $attempt['userId'];

    $firstName = trim($attempt['studFirstName'] ?? '');
    $middleName = trim($attempt['studMiddleName'] ?? '');
    $lastName = trim($attempt['studLastName'] ?? '');
    $extName = trim($attempt['studExtName'] ?? '');

    $fullName = trim(
        $firstName . ' ' .
        $middleName . ' ' .
        $lastName . ' ' .
        $extName
    );

    $totalPoints = (int) $attempt['totalPoints'];
    $score = (int) $attempt['score'];

    $percentage = $totalPoints > 0
        ? ($score / $totalPoints) * 100
        : 0;

    if (!isset($students[$userId])) {
        $students[$userId] = [
            'userId' => $userId,
            'name' => $fullName,
            'block' => $attempt['blkName'] ?? 'N/A',
            'bestScore' => $score,
            'bestTotal' => $totalPoints,
            'bestPercentage' => $percentage,
            'bestDate' => $attempt['endedAt'],
            'attempts' => []
        ];
    }

    $students[$userId]['attempts'][] = [
        'gameId' => $attempt['gameId'],
        'score' => $score,
        'totalPoints' => $totalPoints,
        'percentage' => $percentage,
        'startedAt' => $attempt['startedAt'],
        'endedAt' => $attempt['endedAt']
    ];
}

// Calculate summary statistics
$totalParticipants = count($students);
$totalAttempts = count($attemptRows);

$averageScore = 0;
$highestScore = 0;

if ($totalParticipants > 0) {
    $bestScores = [];

    foreach ($students as $student) {
        $bestScores[] = $student['bestPercentage'];

        if ($student['bestPercentage'] > $highestScore) {
            $highestScore = $student['bestPercentage'];
        }
    }

    $averageScore = array_sum($bestScores) / $totalParticipants;
}

// Date formatter
function formatResultDate($date) {
    if (empty($date)) {
        return 'Not available';
    }

    try {
        $utcDate = new DateTime(
            $date,
            new DateTimeZone('UTC')
        );

        $utcDate->setTimezone(new DateTimeZone('Asia/Manila'));

        return $utcDate->format('M d, Y h:i A');

    } catch (Exception $e) {
        return 'Not available';
    }
}

// Escape output
function resultEscape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

include __DIR__ . '/includes/header.php';
?>

<style>
.results-page {
    display: flex;
    flex-direction: column;
    gap: 22px;
}

.results-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.results-top h2 {
    margin: 0;
    color: var(--ink, #1A1A1A);
}

.results-top p {
    margin: 5px 0 0;
    color: var(--muted, #7A6A5E);
}

.results-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 16px;
    border-radius: 9px;
    background: var(--soft, #FAEEE3);
    color: var(--ink, #1A1A1A);
    text-decoration: none;
    font-weight: 600;
    border: 1px solid var(--line, #E3D0BF);
}

.results-back:hover {
    background: var(--panel, #F3E3D5);
}

.results-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.result-stat {
    background: var(--panel, #F3E3D5);
    border: 1px solid var(--line, #E3D0BF);
    border-radius: 12px;
    padding: 18px;
}

.result-stat .stat-label {
    color: var(--muted, #7A6A5E);
    font-size: 13px;
    margin-bottom: 9px;
}

.result-stat .stat-value {
    color: var(--ink, #1A1A1A);
    font-size: 26px;
    font-weight: 700;
}

.results-section {
    background: var(--side, #fff);
    border: 1px solid var(--line, #E3D0BF);
    border-radius: 12px;
    padding: 20px;
}

.results-section h3 {
    margin: 0 0 5px;
    color: var(--ink, #1A1A1A);
}

.results-description {
    color: var(--muted, #7A6A5E);
    font-size: 13px;
    margin-bottom: 18px;
}

.results-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.results-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

.results-table th {
    text-align: left;
    color: var(--muted, #7A6A5E);
    font-size: 12px;
    font-weight: 700;
    padding: 13px 12px;
    border-bottom: 1px solid var(--line, #E3D0BF);
    white-space: nowrap;
}

.results-table td {
    padding: 15px 12px;
    border-bottom: 1px solid var(--line, #E3D0BF);
    color: var(--ink, #1A1A1A);
    font-size: 13px;
    vertical-align: middle;
}

.results-table tr:last-child td {
    border-bottom: none;
}

.student-name {
    font-weight: 700;
}

.score-main {
    font-weight: 700;
}

.score-percent {
    display: block;
    color: var(--muted, #7A6A5E);
    font-size: 12px;
    margin-top: 3px;
}

.see-more {
    color: var(--o, #FF6A00);
    font-weight: 700;
    cursor: pointer;
    border: none;
    background: none;
    font-size: 13px;
    padding: 5px 0;
}

.see-more:hover {
    text-decoration: underline;
}

.attempt-details {
    background: var(--soft, #FAEEE3);
}

.attempt-details td {
    padding: 16px 22px;
}

.attempt-history-title {
    font-weight: 700;
    margin-bottom: 10px;
}

.attempt-history {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.attempt-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 11px 13px;
    background: #fff;
    border: 1px solid var(--line, #E3D0BF);
    border-radius: 8px;
}

.attempt-item-left {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.attempt-label {
    font-size: 12px;
    color: var(--muted, #7A6A5E);
}

.attempt-score {
    font-weight: 700;
}

.best-label {
    display: inline-block;
    background: var(--o, #FF6A00);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 4px 7px;
    border-radius: 5px;
    margin-left: 6px;
}

.results-empty {
    text-align: center;
    padding: 40px 15px;
    color: var(--muted, #7A6A5E);
}

@media (max-width: 850px) {
    .results-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 480px) {
    .results-summary {
        grid-template-columns: 1fr;
    }

    .results-section {
        padding: 14px;
    }
}
</style>

<div class="results-page">

    <div class="results-top">
        <div>
            <h2>Quiz Results</h2>
            <p>
                <?= resultEscape($quiz['title'] ?? 'Untitled Quiz') ?>
            </p>
        </div>

        <a href="quiz1.php?tab=pub" class="results-back">
            Back to Published
        </a>
    </div>

    <div class="results-summary">

        <div class="result-stat">
            <div class="stat-label">Total Participants</div>
            <div class="stat-value">
                <?= number_format($totalParticipants) ?>
            </div>
        </div>

        <div class="result-stat">
            <div class="stat-label">Average Best Score</div>
            <div class="stat-value">
                <?= number_format($averageScore, 1) ?>%
            </div>
        </div>

        <div class="result-stat">
            <div class="stat-label">Highest Best Score</div>
            <div class="stat-value">
                <?= number_format($highestScore, 1) ?>%
            </div>
        </div>

        <div class="result-stat">
            <div class="stat-label">Total Attempts</div>
            <div class="stat-value">
                <?= number_format($totalAttempts) ?>
            </div>
        </div>

    </div>

    <section class="results-section">

        <h3>Student Results</h3>

        <div class="results-description">
            Each student is counted once. The best attempt is used for the
            displayed score, average, and highest score. Retakes are included
            in the total attempts.
        </div>

        <?php if (empty($students)): ?>

            <div class="results-empty">
                <h3>No Results Yet</h3>
                <p>No students have submitted this quiz.</p>
            </div>

        <?php else: ?>

            <div class="results-table-wrap">

                <table class="results-table">

                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Block / Section</th>
                            <th>Best Score</th>
                            <th>Attempts</th>
                            <th>Best Attempt Date</th>
                            <th>History</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($students as $student): ?>

                        <?php
                        $attemptCount = count($student['attempts']);
                        $detailsId = 'attempts-' . (int) $student['userId'];
                        ?>

                        <tr>
                            <td class="student-name">
                                <?= resultEscape($student['name']) ?>
                            </td>

                            <td>
                                <?= resultEscape($student['block']) ?>
                            </td>

                            <td>
                                <span class="score-main">
                                    <?= $student['bestScore'] ?>
                                    /
                                    <?= $student['bestTotal'] ?>
                                </span>

                                <span class="score-percent">
                                    <?= number_format($student['bestPercentage'], 1) ?>%
                                </span>
                            </td>

                            <td>
                                <?= $attemptCount ?>
                            </td>

                            <td>
                                <?= resultEscape(
                                    formatResultDate($student['bestDate'])
                                ) ?>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="see-more"
                                    onclick="
                                        const row = document.getElementById('<?= resultEscape($detailsId) ?>');
                                        const isHidden = row.hidden;
                                        row.hidden = !isHidden;
                                        this.textContent = isHidden ? 'See Less' : 'See More';
                                    "
                                >
                                    See More
                                </button>
                            </td>
                        </tr>

                        <tr
                            id="<?= resultEscape($detailsId) ?>"
                            class="attempt-details"
                            hidden
                        >
                            <td colspan="6">

                                <div class="attempt-history-title">
                                    Attempt History — <?= resultEscape($student['name']) ?>
                                </div>

                                <div class="attempt-history">

                                    <?php foreach ($student['attempts'] as $index => $attempt): ?>

                                        <div class="attempt-item">

                                            <div class="attempt-item-left">

                                                <span class="attempt-label">
                                                    Attempt <?= $index + 1 ?>

                                                    <?php if ($index === 0): ?>
                                                        <span class="best-label">
                                                            BEST
                                                        </span>
                                                    <?php endif; ?>
                                                </span>

                                                <span class="attempt-score">
                                                    <?= $attempt['score'] ?>
                                                    /
                                                    <?= $attempt['totalPoints'] ?>
                                                    (<?= number_format($attempt['percentage'], 1) ?>%)
                                                </span>

                                            </div>

                                            <div class="attempt-label">
                                                Submitted:
                                                <?= resultEscape(
                                                    formatResultDate($attempt['endedAt'])
                                                ) ?>
                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
