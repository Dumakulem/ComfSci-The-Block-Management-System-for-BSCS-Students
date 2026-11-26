<?php
// Loads quiz data and renders the administrator's quiz management interface.
require_once __DIR__ . '/includes/init.php';

$PAGE_TITLE = 'Gamified Quiz';
$PAGE_ICON = 'bars';

$tab = $_GET['tab'] ?? 'create';

if (!in_array($tab, ['create', 'saved', 'pub'], true)) {
    $tab = 'create';
}

function quizEditorType(string $type): string
{
    $normalizedType = strtolower(trim($type));

    if ($normalizedType === 'true/false' || $normalizedType === 'tf') {
        return 'tf';
    }

    return str_contains($normalizedType, 'choice') ? 'mc' : 'id';
}

$stmt = $pdo->query("SELECT * FROM quiz");
$all = $stmt->fetchAll();

$edit_id = (int)($_GET['edit'] ?? 0);
$edit_quiz_data = null;

if ($edit_id > 0) {
    $stmt_edit = $pdo->prepare("SELECT * FROM quiz WHERE quizId = ?");
    $stmt_edit->execute([$edit_id]);
    $edit_quiz_data = $stmt_edit->fetch();

    if ($edit_quiz_data) {
        $stmt_eq = $pdo->prepare("
            SELECT * FROM question
            WHERE quizId = ?
            ORDER BY questionId ASC
        ");

        $stmt_eq->execute([$edit_id]);
        $questions = $stmt_eq->fetchAll();

        $formatted_qs = [];

        foreach ($questions as $q) {
            $questionType = quizEditorType((string) $q['type']);
            $choices = $questionType === 'tf'
                ? ['True', 'False']
                : json_decode($q['choices'] ?? '', true);

            if (!is_array($choices)) {
                $choices = ['', '', '', ''];
            }

            while (count($choices) < 4) {
                $choices[] = '';
            }

            $correct = $q['correct_answer'] ?? '';
            $correctIndex = array_search($correct, $choices, true);

            $formatted_qs[] = [
                'type' => $questionType,
                'q' => $q['questionText'],
                'ch' => array_slice($choices, 0, 4),
                'ok' => ($correctIndex === false) ? -1 : $correctIndex,
                'ans' => $correct
            ];
        }

        if (empty($formatted_qs)) {
            $formatted_qs[] = [
                'type' => 'mc',
                'q' => '',
                'ch' => ['', '', '', ''],
                'ok' => -1,
                'ans' => ''
            ];
        }

        $edit_quiz_data['qs'] = $formatted_qs;

        $edit_quiz_data['timer'] =
            (int)($edit_quiz_data['timer'] ?? 30);

        $edit_quiz_data['points'] =
            (int)($edit_quiz_data['points'] ?? 10);

        $edit_quiz_data['xp_per_correct'] =
            (int)($edit_quiz_data['xp_per_correct'] ?? 5);

        $edit_quiz_data['due'] =
            $edit_quiz_data['due_date'] ?? date('Y-m-d');

        $edit_quiz_data['assign'] = 'BSCS 2B';

        $edit_quiz_data['allow_retake'] =
            (int)($edit_quiz_data['allow_retake'] ?? 0);

        $edit_quiz_data['show_answer_feedback'] =
            (int)($edit_quiz_data['show_answer_feedback'] ?? 0);

        $tab = 'create';
    }
}

// Get submission statistics.
$submissions_data = [];

try {
    $sub_stmt = $pdo->query("
        SELECT
            quizId,
            COUNT(*) AS total_subs,
            AVG(score) AS avg_score
        FROM gamequiz
        GROUP BY quizId
    ");

    while ($row = $sub_stmt->fetch()) {
        $submissions_data[$row['quizId']] = $row;
    }
} catch (Exception $e) {
}

// Load every quiz's questions so the Publish popup works
// (quiz.js reads z.qs, z.due, z.assign, z.timer and z.points).
$questions_by_quiz = [];

foreach ($pdo->query("SELECT * FROM question ORDER BY questionId ASC") as $q) {
    $questionType = quizEditorType((string) $q['type']);
    $choices = $questionType === 'tf'
        ? ['True', 'False']
        : json_decode($q['choices'] ?? '', true);

    if (!is_array($choices)) {
        $choices = ['', '', '', ''];
    }

    while (count($choices) < 4) {
        $choices[] = '';
    }

    $correct = $q['correct_answer'] ?? '';
    $idx = array_search($correct, $choices, true);

    $questions_by_quiz[$q['quizId']][] = [
        'type' => $questionType,
        'q'    => $q['questionText'],
        'ch'   => array_slice($choices, 0, 4),
        'ok'   => ($idx === false) ? -1 : $idx,
        'ans'  => $correct
    ];
}

// Add statistics and default values to quizzes.
foreach ($all as &$z) {
    $qid = $z['quizId'] ?? 0;

    $z['sub'] =
        $submissions_data[$qid]['total_subs'] ?? 0;

    $z['avg_score'] = isset($submissions_data[$qid]['avg_score'])
        ? round($submissions_data[$qid]['avg_score'])
        : 0;

    $z['allow_retake'] =
        (int)($z['allow_retake'] ?? 0);

    $z['show_answer_feedback'] =
        (int)($z['show_answer_feedback'] ?? 0);

    $z['xp_per_correct'] =
        (int)($z['xp_per_correct'] ?? 5);

    // Fields the Publish / Unpublish popup needs.
    $z['qs']     = $questions_by_quiz[$qid] ?? [];
    $z['timer']  = (int)($z['timer'] ?? 30) ?: 30;
    $z['points'] = (int)($z['points'] ?? 10) ?: 10;
    $z['due']    = $z['due_date'] ?? '';
    $z['assign'] = 'BSCS 2B';
}

unset($z);

// Separate drafts and published quizzes.
$saved = array_values(array_filter(
    $all,
    fn($z) => ($z['status'] ?? 'draft') === 'draft'
));

$pub = array_values(array_filter(
    $all,
    fn($z) => ($z['status'] ?? '') === 'published'
));

$scored_pubs = array_values(array_filter(
    $pub,
    fn($z) => $z['sub'] > 0
));

if (count($scored_pubs) > 0) {
    $total_sum = array_sum(array_column($scored_pubs, 'avg_score'));
    $avg = round($total_sum / count($scored_pubs)) . '%';
} else {
    $avg = '—';
}

// Clear draft when requested.
$clearDraft = !empty($_SESSION['clear_draft']);
unset($_SESSION['clear_draft']);

// Safely pass PHP data to JavaScript.
$J = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

// quiz.js looks quizzes up by quizId (Q[id]), so key the list by quizId.
$quizzes_by_id = [];
foreach ($all as $z) {
    $quizzes_by_id[$z['quizId']] = $z;
}

$quizData = [
    'tab' => $tab,
    'today' => date('Y-m-d'),
    'clearDraft' => $clearDraft,
    'quizzes' => (object)$quizzes_by_id,
    'editQuiz' => $edit_quiz_data,
];

$PAGE_SCRIPTS =
    '<script>window.QUIZ=' .
    json_encode($quizData, $J) .
    ';</script>' .
    '<script src="js/quiz.js?v=7"></script>';

$tabs = [
    'create' => 'Create',
    'saved' => 'Saved',
    'pub' => 'Published'
];

include __DIR__ . '/includes/header.php';
?>

<section class="stats">
    <div class="st">
        <small>Total quizzes</small>
        <b><?= count($all) ?></b>
    </div>

    <div class="st">
        <small>Published</small>
        <b><?= count($pub) ?></b>
    </div>

    <div class="st">
        <small>Drafts</small>
        <b><?= count($saved) ?></b>
    </div>

    <div class="st dk">
        <small>Average score</small>
        <b><?= $avg ?></b>
    </div>
</section>

<section class="panel <?= $tab === 'pub' ? 'pub-panel' : '' ?>" id="view">

    <?php if ($tab === 'create'): ?>

        <h1><?= $edit_id ? 'Edit quiz' : 'Create quiz' ?></h1>

        <p class="sub">
            <?= $edit_id
                ? 'Modify your quiz details below and save changes.'
                : 'Choose a quiz type, fill in the answers, then save and publish.' ?>
        </p>

    <?php elseif ($tab === 'saved'): ?>

        <h1>Saved quizzes</h1>
        <p class="sub">Drafts you have saved but not published yet.</p>

    <?php else: ?>

        <h1>Published quizzes</h1>
        <p class="sub">Quizzes currently visible to students.</p>

    <?php endif; ?>

    <div class="tabs">
        <?php foreach ($tabs as $k => $label): ?>

            <a
                class="tab <?= $tab === $k ? 'on' : '' ?>"
                href="quiz1.php?tab=<?= $k ?>"
            >
                <?= $label ?>
            </a>

        <?php endforeach; ?>
    </div>

    <?php if ($tab === 'create'): ?>

        <div id="builder"></div>

    <?php elseif ($tab === 'saved'): ?>

        <?php if (!$saved): ?>
            <p class="note">No drafts yet.</p>
        <?php endif; ?>

        <?php foreach ($saved as $z):

            $qid = $z['quizId'] ?? 0;
            $title = htmlspecialchars(
                $z['title'] ?? 'Untitled',
                ENT_QUOTES
            );

        ?>

            <div class="row">

                <div class="m">
                    <b><?= h($z['title'] ?? 'Untitled') ?></b>
                    <span class="pill d">DRAFT</span>
                </div>

                <button
                    type="button"
                    class="btn dl"
                    onclick="openDeleteModal(<?= $qid ?>, '<?= $title ?>', 'saved')"
                >
                    Delete
                </button>

                <a
                    href="quiz1.php?tab=create&edit=<?= $qid ?>"
                    class="btn"
                >
                    Edit
                </a>

                <button
                    type="button"
                    class="btn o"
                    data-a="pubsaved"
                    data-id="<?= $qid ?>"
                >
                    Publish
                </button>

            </div>

        <?php endforeach; ?>

        <button class="dash" data-a="new">
            + Create new quiz
        </button>

    <?php else: ?>

        <div class="pub-list">

            <?php if (!$pub): ?>
                <p class="note">Nothing is published yet.</p>
            <?php endif; ?>

            <?php foreach ($pub as $z):

                $qid = $z['quizId'] ?? 0;
                $title = htmlspecialchars(
                    $z['title'] ?? 'Untitled',
                    ENT_QUOTES
                );

            ?>

                <div class="pub-item">

                    <div class="row">

                        <div class="m">
                            <b><?= h($z['title'] ?? 'Untitled') ?></b>
                            <span class="pill p">PUBLISHED</span>
                        </div>

                        <a
                            href="quiz_result.php?id=<?= $qid ?>"
                            class="btn"
                        >
                            View results
                        </a>

                        <a
                            href="quiz1.php?tab=create&edit=<?= $qid ?>"
                            class="btn"
                        >
                            Edit
                        </a>

                        <button
                            type="button"
                            class="btn dl"
                            onclick="openDeleteModal(<?= $qid ?>, '<?= $title ?>', 'pub')"
                        >
                            Delete
                        </button>

                        <form
                            action="publish_quiz.php"
                            method="POST"
                            style="display:inline;"
                        >

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= $qid ?>"
                            >

                            <input
                                type="hidden"
                                name="mode"
                                value="unpublish"
                            >

                            <button type="submit" class="btn ol">
                                Unpublish
                            </button>

                        </form>

                    </div>

                    <p class="note">
                        <?= (int)$z['sub'] ?> students have submitted ·

                        <?= $z['sub']
                            ? 'Average score ' . $z['avg_score'] . '%'
                            : 'No scores yet' ?>
                    </p>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<div
    id="customDeleteModal"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;"
>

    <div
        style="background:#fdfaf6; padding:30px; border-radius:20px; width:100%; max-width:420px; box-shadow:0 10px 30px rgba(0,0,0,0.2); text-align:center;"
    >

        <h3
            style="font-size:20px; font-weight:800; color:var(--ink); margin-bottom:10px;"
        >
            Delete this quiz?
        </h3>

        <p
            style="font-size:14px; color:var(--muted); margin-bottom:20px;"
        >
            This action cannot be undone.
            <br>
            <b id="deleteQuizTitle" style="color:var(--ink);"></b>
        </p>

        <form id="deleteForm" action="delete_quiz.php" method="POST">

            <?= csrf_field() ?>

            <input type="hidden" name="id" id="deleteQuizId">
            <input type="hidden" name="tab" id="deleteQuizTab">

            <div style="display:flex; gap:10px; justify-content:center;">

                <button
                    type="button"
                    onclick="closeDeleteModal()"
                    class="btn"
                    style="flex:1; padding:12px; border-radius:12px; font-weight:700;"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn dl"
                    style="flex:1; padding:12px; border-radius:12px; font-weight:700; background:#e74c3c; color:#fff; border:none;"
                >
                    Delete
                </button>

            </div>

        </form>

    </div>

</div>

<script>
function openDeleteModal(id, title, tab) {
    document.getElementById('deleteQuizId').value = id;
    document.getElementById('deleteQuizTitle').innerText = '"' + title + '"';
    document.getElementById('deleteQuizTab').value = tab;

    document.getElementById('customDeleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('customDeleteModal').style.display = 'none';
}
</script>

<div class="ov" id="ov" hidden>
    <div class="modal" id="modal" role="dialog" aria-modal="true"></div>
</div>

<form id="postForm" method="post" hidden>

    <?= csrf_field() ?>

    <input type="hidden" name="quiz">
    <input type="hidden" name="id">
    <input type="hidden" name="mode">
    <input type="hidden" name="publish">
    <input type="hidden" name="allow_retake">
    <input type="hidden" name="show_answer_feedback">

</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
