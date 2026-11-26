
<?php
require_once __DIR__ . '/includes/init.php';

// Get current student.
$stmt_student = $pdo->prepare("SELECT * FROM students WHERE studId = ? LIMIT 1");
$stmt_student->execute([(int) $_SESSION['user_id']]);
$current_student = $stmt_student->fetch();

if (!$current_student) {
    die("No student found.");
}

$userId = (int)$current_student['studId'];

// Update profile avatar.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['avatar'])) {

    $selected_avatar = $_POST['avatar'];

    $allowed_avatars = [
        'caramel.png',
        'cookie.png',
        'fluff.png',
        'matcha.png',
        'mochi.png',
        'taro.png'
    ];

    if (in_array($selected_avatar, $allowed_avatars, true)) {

        $stmt_up = $pdo->prepare("
            UPDATE students
            SET quiz_avatar = ?
            WHERE studId = ?
        ");

        $stmt_up->execute([
            $selected_avatar,
            $userId
        ]);

        flash("Avatar updated successfully!");
    }

    header("Location: student_profile.php");
    exit;
}

// Available avatars.
$avatars = [
    'caramel.png' => 'Caramel (Fox)',
    'cookie.png' => 'Cookie (Cat)',
    'fluff.png' => 'Fluff (Chick)',
    'matcha.png' => 'Matcha (Frog)',
    'mochi.png' => 'Mochi (Rabbit)',
    'taro.png' => 'Taro (Penguin)'
];

$current_avatar = $current_student['quiz_avatar'] ?? 'mochi.png';

if (
    $current_avatar === 'default.png' ||
    !array_key_exists($current_avatar, $avatars)
) {
    $current_avatar = 'mochi.png';
}

// ==========================================
// GET STUDENT COSMETIC REWARDS
// ==========================================

$stmt_rewards = $pdo->prepare("
    SELECT *
    FROM student_cosmetic_rewards
    WHERE userId = ?
    ORDER BY earned_at DESC
");

$stmt_rewards->execute([$userId]);
$rewards = $stmt_rewards->fetchAll();

$totalRewards = count($rewards);

// Reward icons.
$rewardIcons = [
    'sparkle_frame' => '✨',
    'golden_star' => '⭐',
    'confetti_effect' => '🎉',
    'rainbow_badge' => '🌈',
    'flower_charm' => '🌸',
    'moon_glow' => '🌙'
];

// Reward type labels.
$rewardTypes = [
    'badge' => 'Badge',
    'profile_effect' => 'Profile Effect',
    'cosmetic' => 'Cosmetic'
];

$PAGE_TITLE = 'Student Profile & Avatar';

include __DIR__ . '/includes/header.php';
?>

<style>
.profile-container {
    width: 100%;
    max-width: 850px;
    margin: 0 auto;
}

.profile-section {
    background: var(--field);
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 28px;
    margin-bottom: 25px;
    box-sizing: border-box;
}

.profile-section h2 {
    color: var(--ink);
    font-size: 21px;
    font-weight: 800;
    margin: 0 0 8px;
}

.profile-description {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 24px;
}

.avatar-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.avatar-option {
    cursor: pointer;
    text-align: center;
    background: var(--soft);
    border-radius: 16px;
    padding: 20px 10px;
    border: 2px solid transparent;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    transition: .2s;
}

.avatar-option:hover {
    border-color: var(--o);
    transform: translateY(-3px);
}

.avatar-option.selected {
    border-color: var(--o);
    background: #fff0e3;
}

.avatar-option input {
    display: none;
}

.avatar-option img {
    width: 90px;
    height: 90px;
    object-fit: cover;
    border-radius: 50%;
    box-shadow: 0 4px 12px rgba(0,0,0,.1);
}

.avatar-option b {
    font-size: 13px;
    color: var(--ink);
}

/* COLLECTION */

.collection-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 22px;
}

.collection-count {
    background: #fff0e3;
    color: var(--o);
    border-radius: 30px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
}

.reward-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.reward-card {
    background: var(--soft);
    border: 1px solid var(--line);
    border-radius: 17px;
    padding: 20px 12px;
    text-align: center;
    transition: .2s;
}

.reward-card:hover {
    transform: translateY(-3px);
    border-color: var(--o);
}

.reward-icon {
    width: 65px;
    height: 65px;
    margin: 0 auto 12px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
}

.reward-card h3 {
    color: var(--ink);
    font-size: 14px;
    font-weight: 800;
    margin: 0 0 7px;
}

.reward-type {
    display: inline-block;
    background: #fff0e3;
    color: #b95b18;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    margin-bottom: 10px;
}

.reward-date {
    display: block;
    color: var(--muted);
    font-size: 11px;
}

.empty-collection {
    text-align: center;
    padding: 45px 20px;
    background: var(--soft);
    border: 1px dashed var(--line);
    border-radius: 17px;
}

.empty-collection .empty-icon {
    font-size: 45px;
    margin-bottom: 12px;
}

.empty-collection h3 {
    color: var(--ink);
    font-size: 17px;
    margin: 0 0 8px;
}

.empty-collection p {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.5;
    margin: 0;
}

@media(max-width: 600px) {

    .profile-section {
        padding: 20px 15px;
    }

    .avatar-grid {
        gap: 10px;
    }

    .avatar-option {
        padding: 15px 5px;
    }

    .avatar-option img {
        width: 65px;
        height: 65px;
    }

    .avatar-option b {
        font-size: 11px;
    }

    .reward-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .collection-header {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<div class="profile-container">

    <!-- AVATAR SELECTION -->

    <section class="panel profile-section">

        <h2>Choose Your Avatar</h2>

        <p class="profile-description">
            Select your favorite character icon to represent you across the platform.
        </p>

        <form action="student_profile.php" method="POST">

            <div class="avatar-grid">

                <?php foreach ($avatars as $file => $name): ?>

                    <label class="avatar-option <?= $current_avatar === $file ? 'selected' : '' ?>">

                        <input
                            type="radio"
                            name="avatar"
                            value="<?= htmlspecialchars($file) ?>"
                            <?= $current_avatar === $file ? 'checked' : '' ?>
                            onchange="this.form.submit()"
                        >

                        <img
                            src="avatar/<?= htmlspecialchars($file) ?>"
                            alt="<?= htmlspecialchars($name) ?>"
                        >

                        <b><?= htmlspecialchars($name) ?></b>

                    </label>

                <?php endforeach; ?>

            </div>

        </form>

    </section>


    <!-- MY COLLECTION -->

    <section class="panel profile-section">

        <div class="collection-header">

            <div>
                <h2>My Collection</h2>

                <p class="profile-description" style="margin: 5px 0 0;">
                    Collect badges and cosmetic rewards from Fluff's Lucky Spin.
                </p>
            </div>

            <span class="collection-count">
                <?= $totalRewards ?> Collected
            </span>

        </div>

        <?php if ($totalRewards > 0): ?>

            <div class="reward-grid">

                <?php foreach ($rewards as $reward):

                    $code = $reward['reward_code'];

                    $icon = $rewardIcons[$code] ?? '🎁';

                    $type = $rewardTypes[$reward['reward_type']]
                        ?? 'Cosmetic';

                    $earnedDate = date(
                        'M d, Y',
                        strtotime($reward['earned_at'])
                    );

                ?>

                    <div class="reward-card">

                        <div class="reward-icon">
                            <?= $icon ?>
                        </div>

                        <h3>
                            <?= htmlspecialchars($reward['reward_name']) ?>
                        </h3>

                        <span class="reward-type">
                            <?= htmlspecialchars($type) ?>
                        </span>

                        <span class="reward-date">
                            Earned <?= htmlspecialchars($earnedDate) ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-collection">

                <div class="empty-icon">🎁</div>

                <h3>Your collection is empty</h3>

                <p>
                    You haven't collected any cosmetic rewards yet.
                    Choose Fluff before starting a quiz and try the Lucky Spin!
                </p>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
