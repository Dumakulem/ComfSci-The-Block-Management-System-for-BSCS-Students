
<?php
require_once __DIR__ . '/includes/init.php';

// Get current student.
$stmt_student = $pdo->prepare("SELECT * FROM students WHERE studId = ? LIMIT 1");
$stmt_student->execute([(int) $_SESSION['user_id']]);
$current_student = $stmt_student->fetch();

if (!$current_student) {
    die("No student found in the database.");
}

$PAGE_TITLE = 'Gamified Quiz';

$tab = $_GET['tab'] ?? 'available';

if (!in_array($tab, ['available', 'completed', 'leaderboard'], true)) {
    $tab = 'available';
}

$studId = $current_student['studId'];

// Avatar settings.
$avatarOptions = [
    'caramel.png' => 'Caramel',
    'cookie.png'  => 'Cookie',
    'fluff.png'   => 'Fluff',
    'matcha.png'  => 'Matcha',
    'mochi.png'   => 'Mochi',
    'taro.png'    => 'Taro'
];

$avatarFolder = 'avatar/';
$currentAvatar = $current_student['quiz_avatar'] ?? 'mochi.png';

if (!array_key_exists($currentAvatar, $avatarOptions)) {
    $currentAvatar = 'mochi.png';
}

// Create CSRF token.
if (empty($_SESSION['avatar_csrf'])) {
    $_SESSION['avatar_csrf'] = bin2hex(random_bytes(32));
}

// Save avatar selection.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_avatar'])) {

    $submittedToken = $_POST['avatar_csrf'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['avatar_csrf'], $submittedToken)
    ) {
        die("Invalid request. Please refresh the page and try again.");
    }

    $selectedAvatar = $_POST['quiz_avatar'] ?? '';

    if (!array_key_exists($selectedAvatar, $avatarOptions)) {
        die("Invalid avatar selection.");
    }

    $updateAvatar = $pdo->prepare("
        UPDATE students
        SET quiz_avatar = ?
        WHERE studId = ?
    ");

    $updateAvatar->execute([$selectedAvatar, $studId]);

    header("Location: student_dashboard.php?tab=" . urlencode($tab) . "&avatar_saved=1");
    exit;
}

// Get published quizzes.
$stmt_quizzes = $pdo->prepare("
    SELECT *
    FROM quiz
    WHERE status = 'published'
");
$stmt_quizzes->execute();
$all_pub = $stmt_quizzes->fetchAll();

// Get completed quizzes.
$stmt_done = $pdo->prepare("
    SELECT DISTINCT quizId
    FROM gamequiz
    WHERE userId = ?
");
$stmt_done->execute([$studId]);
$completed_quiz_ids = $stmt_done->fetchAll(PDO::FETCH_COLUMN);

// Separate available and completed quizzes.
$available_quizzes = array_filter(
    $all_pub,
    fn($z) => !in_array($z['quizId'], $completed_quiz_ids)
        || ($z['allow_retake'] == 1)
);

$completed_quizzes = array_filter(
    $all_pub,
    fn($z) => in_array($z['quizId'], $completed_quiz_ids)
        && ($z['allow_retake'] != 1)
);

$total_pub = count($all_pub);
$completed_count = count($completed_quiz_ids);

// Calculate student rank.
$stmt_rank = $pdo->prepare("
    SELECT COUNT(*) + 1
    FROM students
    WHERE total_xp > ?
");
$stmt_rank->execute([$current_student['total_xp'] ?? 0]);

$rank = $stmt_rank->fetchColumn() ?: 1;
$total_xp = (int)($current_student['total_xp'] ?? 0);

// Get leaderboard.
$leaderboard = [];

if ($tab === 'leaderboard') {
    try {
        $stmt_lb = $pdo->query("
            SELECT s.*,
                   COUNT(DISTINCT g.quizId) AS completed_quizzes
            FROM students s
            LEFT JOIN gamequiz g ON s.studId = g.userId
            GROUP BY s.studId
            ORDER BY s.total_xp DESC
            LIMIT 10
        ");

        $leaderboard = $stmt_lb->fetchAll();

    } catch (Exception $e) {
        $stmt_lb = $pdo->query("
            SELECT *
            FROM students
            ORDER BY total_xp DESC
            LIMIT 10
        ");

        $leaderboard = $stmt_lb->fetchAll();
    }
}

// Get avatar image path.
$getAvatar = function($student) use ($avatarOptions, $avatarFolder) {

    $avatar = $student['quiz_avatar'] ?? 'mochi.png';

    if (!array_key_exists($avatar, $avatarOptions)) {
        $avatar = 'mochi.png';
    }

    return $avatarFolder . $avatar;
};

include __DIR__ . '/includes/header.php';
?>

<style>
/* LEADERBOARD TEXT FIX */
.leaderboard-box,
.leaderboard-box * {
    color: #fff !important;
}

.leaderboard-box .podium-name {
    color: #fff !important;
}

.leaderboard-box .podium-rank {
    color: #1e1e1e !important;
}

.leaderboard-box table {
    color: #fff !important;
}

.leaderboard-box th {
    color: #aaa !important;
}

.leaderboard-box td,
.leaderboard-box td span {
    color: #fff !important;
}

.leaderboard-box tr {
    border-color: #444 !important;
}
</style>

<!-- STUDENT STATISTICS -->
<section class="stats" style="grid-template-columns: repeat(4, 1fr);">

    <div class="st">
        <small>Available Quizzes</small>
        <b><?= count($available_quizzes) ?></b>
    </div>

    <div class="st">
        <small>Completed Quizzes</small>
        <b><?= $completed_count ?></b>
    </div>

    <div class="st">
        <small>Rank</small>
        <b>#<?= $rank ?></b>
    </div>

    <div class="st dk">
        <small>XP</small>
        <b><?= $total_xp ?> XP</b>
    </div>

</section>

<!-- MAIN DASHBOARD -->
<section class="panel">

    <div style="
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    ">

        <div>
            <?php if ($tab === 'available'): ?>
                <h1>Available quizzes</h1>
                <p class="sub">Select a quiz below to start answering and earn XP.</p>

            <?php elseif ($tab === 'completed'): ?>
                <h1>Completed quizzes</h1>
                <p class="sub">View the quizzes you have already finished.</p>

            <?php else: ?>
                <h1>Leaderboard</h1>
                <p class="sub">View student rankings and total XP.</p>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 8px;">
            <a class="tab <?= $tab === 'available' ? 'on' : '' ?>"
               href="student_dashboard.php?tab=available"
               style="text-decoration:none;">Available</a>

            <a class="tab <?= $tab === 'completed' ? 'on' : '' ?>"
               href="student_dashboard.php?tab=completed"
               style="text-decoration:none;">Completed</a>

            <a class="tab <?= $tab === 'leaderboard' ? 'on' : '' ?>"
               href="student_dashboard.php?tab=leaderboard"
               style="text-decoration:none;">Leaderboard</a>
        </div>

    </div>

    <?php if (isset($_GET['avatar_saved'])): ?>
        <p style="
            color: #267344;
            background: #e8f7ed;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
        ">
            Avatar saved successfully!
        </p>
    <?php endif; ?>

    <?php if ($tab === 'available' || $tab === 'completed'): ?>

        <?php
        $display_quizzes = ($tab === 'available')
            ? $available_quizzes
            : $completed_quizzes;
        ?>

        <?php if (empty($display_quizzes)): ?>

            <p class="note" style="text-align: center; padding: 40px;">
                <?= $tab === 'available'
                    ? 'No available quizzes right now.'
                    : 'You haven’t completed any quizzes yet.' ?>
            </p>

        <?php else: ?>

            <div style="
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 16px;
                margin-top: 10px;
            ">

                <?php foreach ($display_quizzes as $z):

                    $qid = $z['quizId'] ?? 0;
                    $is_completed = in_array($qid, $completed_quiz_ids);
                    $can_retake = ($z['allow_retake'] == 1);

                ?>

                    <div style="
                        background: var(--field);
                        border-radius: 16px;
                        padding: 20px;
                        display: flex;
                        flex-direction: column;
                        justify-content: space-between;
                        gap: 14px;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
                    ">

                        <div>
                            <b style="
                                font-size: 16px;
                                display: block;
                                margin-bottom: 12px;
                            ">
                                <?= h($z['title'] ?? 'Untitled Quiz') ?>
                            </b>

                            <div style="
                                display: flex;
                                justify-content: space-between;
                                font-size: 12px;
                                color: var(--muted);
                                margin-bottom: 6px;
                            ">
                                <span>Status</span>

                                <strong style="
                                    color: <?= $is_completed && !$can_retake
                                        ? '#27ae60'
                                        : 'var(--ink)' ?>;
                                ">
                                    <?= $is_completed
                                        ? ($can_retake
                                            ? 'Completed (Retake allowed)'
                                            : 'Completed')
                                        : 'Pending' ?>
                                </strong>
                            </div>
                        </div>

                        <?php if (!$is_completed || $can_retake): ?>
                            <a
                                href="take_quiz.php?id=<?= htmlspecialchars($qid) ?>"
                                class="btn o"
                                style="
                                    text-align: center;
                                    text-decoration: none;
                                    display: block;
                                    margin-top: 8px;
                                "
                            >
                                <?= $is_completed ? 'Retake quiz' : 'Start quiz' ?>
                            </a>
                        <?php else: ?>
                            <button
                                class="btn"
                                style="
                                    text-align: center;
                                    display: block;
                                    margin-top: 8px;
                                    background: #e2d8ce;
                                    color: #555;
                                    cursor: default;
                                "
                                disabled
                            >
                                Completed
                            </button>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    <?php else: ?>

        <?php if (!$leaderboard): ?>

            <p class="note" style="
                margin-top: 20px;
                text-align: center;
                padding: 40px;
            ">
                No leaderboard data available at the moment.
            </p>

        <?php else: ?>

            <?php
            $first = $leaderboard[0] ?? null;
            $second = $leaderboard[1] ?? null;
            $third = $leaderboard[2] ?? null;
            ?>

            <div class="leaderboard-box" style="
                background: #1e1e1e;
                border-radius: 20px;
                padding: 24px;
                color: #fff;
                margin-top: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            ">

                <!-- PODIUM -->
                <div style="
                    display: flex;
                    justify-content: center;
                    align-items: flex-end;
                    gap: 16px;
                    margin-bottom: 30px;
                    padding-top: 20px;
                ">

                    <?php if ($second): ?>
                        <div style="
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            width: 100px;
                        ">

                            <div style="margin-bottom: 8px; text-align: center;">

                                <img
                                    src="<?= h($getAvatar($second)) ?>"
                                    alt="Avatar"
                                    style="
                                        width: 50px;
                                        height: 50px;
                                        border-radius: 50%;
                                        object-fit: cover;
                                        background: #fff;
                                        border: 2px solid #c0c0c0;
                                    "
                                >

                                <div class="podium-name" style="
                                    font-size: 12px;
                                    font-weight: 700;
                                    margin-top: 4px;
                                    white-space: nowrap;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    max-width: 90px;
                                    color: #fff;
                                ">
                                    <?= h($second['studFirstName'] ?? 'Student 2') ?>
                                </div>

                            </div>

                            <div class="podium-rank" style="
                                background: #a0a0a0;
                                width: 100%;
                                height: 90px;
                                border-top-left-radius: 8px;
                                border-top-right-radius: 8px;
                                display: flex;
                                align-items: flex-start;
                                justify-content: center;
                                padding-top: 10px;
                                font-weight: 800;
                                color: #1e1e1e;
                            ">2nd</div>

                        </div>
                    <?php endif; ?>

                    <?php if ($first): ?>
                        <div style="
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            width: 110px;
                        ">

                            <div style="margin-bottom: 8px; text-align: center;">

                                <img
                                    src="<?= h($getAvatar($first)) ?>"
                                    alt="Avatar"
                                    style="
                                        width: 60px;
                                        height: 60px;
                                        border-radius: 50%;
                                        object-fit: cover;
                                        background: #fff;
                                        border: 2px solid gold;
                                    "
                                >

                                <div class="podium-name" style="
                                    font-size: 13px;
                                    font-weight: 700;
                                    margin-top: 4px;
                                    white-space: nowrap;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    max-width: 100px;
                                    color: #fff;
                                ">
                                    <?= h($first['studFirstName'] ?? 'Student 1') ?>
                                </div>

                            </div>

                            <div class="podium-rank" style="
                                background: #d4af37;
                                width: 100%;
                                height: 120px;
                                border-top-left-radius: 8px;
                                border-top-right-radius: 8px;
                                display: flex;
                                align-items: flex-start;
                                justify-content: center;
                                padding-top: 10px;
                                font-weight: 800;
                                color: #1e1e1e;
                            ">1st</div>

                        </div>
                    <?php endif; ?>

                    <?php if ($third): ?>
                        <div style="
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            width: 100px;
                        ">

                            <div style="margin-bottom: 8px; text-align: center;">

                                <img
                                    src="<?= h($getAvatar($third)) ?>"
                                    alt="Avatar"
                                    style="
                                        width: 50px;
                                        height: 50px;
                                        border-radius: 50%;
                                        object-fit: cover;
                                        background: #fff;
                                        border: 2px solid #cd7f32;
                                    "
                                >

                                <div class="podium-name" style="
                                    font-size: 12px;
                                    font-weight: 700;
                                    margin-top: 4px;
                                    white-space: nowrap;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    max-width: 90px;
                                    color: #fff;
                                ">
                                    <?= h($third['studFirstName'] ?? 'Student 3') ?>
                                </div>

                            </div>

                            <div class="podium-rank" style="
                                background: #b08d57;
                                width: 100%;
                                height: 70px;
                                border-top-left-radius: 8px;
                                border-top-right-radius: 8px;
                                display: flex;
                                align-items: flex-start;
                                justify-content: center;
                                padding-top: 10px;
                                font-weight: 800;
                                color: #1e1e1e;
                            ">3rd</div>

                        </div>
                    <?php endif; ?>

                </div>

                <!-- LEADERBOARD TABLE -->
                <div style="width: 100%; overflow-x: auto;">

                    <table style="
                        width: 100%;
                        border-collapse: collapse;
                        text-align: left;
                        font-size: 14px;
                        color: #fff;
                    ">

                        <thead>
                            <tr style="border-bottom: 1px solid #444;">

                                <th style="padding: 10px; color: #aaa !important;">Rank</th>

                                <th style="padding: 10px; color: #aaa !important;">Student Name</th>

                                <th style="padding: 10px; text-align: center; color: #aaa !important;">
                                    Completed Quizzes
                                </th>

                                <th style="padding: 10px; text-align: center; color: #aaa !important;">
                                    XP
                                </th>

                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($leaderboard as $index => $s):

                                $pos = $index + 1;

                                $suffix = ($pos == 1)
                                    ? 'st'
                                    : (($pos == 2)
                                        ? 'nd'
                                        : (($pos == 3) ? 'rd' : 'th'));

                                $comp = $s['completed_quizzes'] ?? 0;
                                $student_xp = (int)($s['total_xp'] ?? 0);
                                $avatarImg = $getAvatar($s);

                            ?>

                                <tr style="border-bottom: 1px solid #2a2a2a; color: #fff;">

                                    <td style="padding: 12px 10px; font-weight: 700; color: #fff !important;">
                                        <?= $pos . $suffix ?>
                                    </td>

                                    <td style="
                                        padding: 12px 10px;
                                        color: #fff !important;
                                    ">

                                        <div style="display: flex; align-items: center; gap: 10px;">

                                            <img
                                                src="<?= h($avatarImg) ?>"
                                                alt="Avatar"
                                                style="
                                                    width: 30px;
                                                    height: 30px;
                                                    border-radius: 50%;
                                                    object-fit: cover;
                                                    background: #fff;
                                                "
                                            >

                                            <span style="color: #fff !important;">
                                                <?= h(
                                                    ($s['studFirstName'] ?? '') .
                                                    ' ' .
                                                    ($s['studLastName'] ?? '')
                                                ) ?>
                                            </span>

                                        </div>

                                    </td>

                                    <td style="
                                        padding: 12px 10px;
                                        text-align: center;
                                        color: #fff !important;
                                    ">
                                        <?= $comp ?>
                                    </td>

                                    <td style="
                                        padding: 12px 10px;
                                        text-align: center;
                                        color: #fff !important;
                                    ">
                                        <?= $student_xp ?> XP
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</section>

<!-- FLOATING AVATAR BUTTON -->
<button
    type="button"
    id="avatar-open-btn"
    title="Change Avatar"
    aria-label="Open avatar selection"
    style="
        position: fixed;
        bottom: 25px;
        right: 25px;
        width: 62px;
        height: 62px;
        border-radius: 50%;
        border: 3px solid var(--o);
        background: #fff;
        padding: 4px;
        cursor: pointer;
        z-index: 999;
        box-shadow: 0 5px 18px rgba(0,0,0,0.2);
        overflow: hidden;
    "
>
    <img
        src="<?= h($avatarFolder . $currentAvatar) ?>"
        alt="Your avatar"
        style="
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        "
    >
</button>

<!-- AVATAR POPUP -->
<div
    id="avatar-modal"
    style="
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.55);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        padding: 20px;
        box-sizing: border-box;
    "
>
    <div style="
        background: #fdfaf6;
        width: 100%;
        max-width: 480px;
        border-radius: 22px;
        padding: 25px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.25);
        position: relative;
        max-height: 90vh;
        overflow-y: auto;
    ">

        <button
            type="button"
            id="avatar-close-btn"
            aria-label="Close avatar selection"
            style="
                position: absolute;
                top: 15px;
                right: 18px;
                border: none;
                background: transparent;
                font-size: 25px;
                color: #777;
                cursor: pointer;
            "
        >&times;</button>

        <h2 style="color: var(--ink); font-size: 21px; margin: 0 0 6px;">
            Choose Your Avatar
        </h2>

        <p style="color: var(--muted); font-size: 13px; margin-bottom: 22px;">
            Choose the character you want to represent you.
        </p>

        <form method="POST" action="student_dashboard.php?tab=<?= h($tab) ?>">

            <input
                type="hidden"
                name="avatar_csrf"
                value="<?= h($_SESSION['avatar_csrf']) ?>"
            >

            <div style="
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
            ">

                <?php foreach ($avatarOptions as $filename => $avatarName): ?>

                    <label style="cursor: pointer;">

                        <input
                            type="radio"
                            name="quiz_avatar"
                            value="<?= h($filename) ?>"
                            <?= $currentAvatar === $filename ? 'checked' : '' ?>
                            style="display: none;"
                            onchange="selectAvatar(this)"
                        >

                        <div
                            class="avatar-choice"
                            style="
                                border: 2px solid <?= $currentAvatar === $filename ? 'var(--o)' : '#e2d8ce' ?>;
                                background: <?= $currentAvatar === $filename ? '#f4e8dc' : '#fff' ?>;
                                border-radius: 15px;
                                padding: 12px 6px;
                                text-align: center;
                                transition: 0.2s;
                            "
                        >

                            <img
                                src="<?= h($avatarFolder . $filename) ?>"
                                alt="<?= h($avatarName) ?>"
                                style="
                                    width: 75px;
                                    height: 75px;
                                    object-fit: contain;
                                    display: block;
                                    margin: 0 auto 8px;
                                "
                            >

                            <strong style="color: var(--ink); font-size: 13px; display: block;">
                                <?= h($avatarName) ?>
                            </strong>

                            <span
                                class="avatar-selected"
                                style="
                                    display: <?= $currentAvatar === $filename ? 'block' : 'none' ?>;
                                    color: var(--o);
                                    font-size: 10px;
                                    font-weight: 800;
                                    margin-top: 5px;
                                "
                            >SELECTED</span>

                        </div>

                    </label>

                <?php endforeach; ?>

            </div>

            <button
                type="submit"
                name="save_avatar"
                value="1"
                class="btn o"
                style="
                    width: 100%;
                    margin-top: 22px;
                    background: var(--o);
                    color: #fff;
                    border: none;
                    border-radius: 20px;
                    padding: 13px;
                    font-weight: 700;
                    cursor: pointer;
                "
            >
                Save Avatar
            </button>

        </form>

    </div>
</div>

<script>
const avatarModal = document.getElementById('avatar-modal');
const avatarOpenBtn = document.getElementById('avatar-open-btn');
const avatarCloseBtn = document.getElementById('avatar-close-btn');

avatarOpenBtn.addEventListener('click', function() {
    avatarModal.style.display = 'flex';
});

avatarCloseBtn.addEventListener('click', function() {
    avatarModal.style.display = 'none';
});

avatarModal.addEventListener('click', function(event) {
    if (event.target === avatarModal) {
        avatarModal.style.display = 'none';
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        avatarModal.style.display = 'none';
    }
});

function selectAvatar(radio) {

    document.querySelectorAll('.avatar-choice').forEach(choice => {

        choice.style.border = '2px solid #e2d8ce';
        choice.style.background = '#fff';

        const selectedLabel = choice.querySelector('.avatar-selected');

        if (selectedLabel) {
            selectedLabel.style.display = 'none';
        }

    });

    const selectedChoice = radio.nextElementSibling;

    if (selectedChoice) {

        selectedChoice.style.border = '2px solid var(--o)';
        selectedChoice.style.background = '#f4e8dc';

        const selectedLabel = selectedChoice.querySelector('.avatar-selected');

        if (selectedLabel) {
            selectedLabel.style.display = 'block';
        }

    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
