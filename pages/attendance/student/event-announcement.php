<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard(['student', 'rep']);

$filter = (string) ($_GET['filter'] ?? 'all');
$sort = (string) ($_GET['sort'] ?? 'newest');

if (!in_array($filter, ['all', 'important', 'upcoming', 'ongoing', 'ended', 'cancelled'], true)) {
    $filter = 'all';
}

if (!in_array($sort, ['newest', 'oldest'], true)) {
    $sort = 'newest';
}

$announcements = $pdo->query(
    'SELECT an.*, a.adminFirstName, a.adminLastName,
            e.eventID AS linkedEventID, e.eventTitle, e.eventDate, e.eventStartTime,
            e.eventEndTime, e.eventLocation, e.eventStatus
     FROM tblannouncement an
     JOIN tbladmin a ON a.adminID = an.adminID
     LEFT JOIN tblevent e ON e.eventID = an.eventID
     WHERE an.eventID IS NULL OR e.archivedAt IS NULL
     ORDER BY an.createdAt DESC'
)->fetchAll();

$events = $pdo->query(
    'SELECT e.*, a.adminFirstName, a.adminLastName
     FROM tblevent e
     JOIN tbladmin a ON a.adminID = e.adminID
     WHERE e.archivedAt IS NULL
     ORDER BY e.eventDate DESC, e.eventStartTime DESC'
)->fetchAll();

/* Mga announcement na naka-link sa event, naka-group per event para sa
   Important reminders kapag pinindot ang event sa listahan. */
$eventAnnouncementMap = [];
foreach ($announcements as $announcement) {
    if ($announcement['linkedEventID'] === null) {
        continue;
    }

    $eventAnnouncementMap[(int) $announcement['linkedEventID']][] = [
        'title' => (string) $announcement['announcementTitle'],
        'content' => (string) $announcement['announcementContent'],
    ];
}

/* Yung general announcements ng admin ang mga importanteng paalala.
   Ang announcement na naka-link sa event ay hindi na nakalista dito kasi
   nasa Campus Events na mismo (event details) at sa Important reminders
   ito lumalabas kapag pinindot ang event. */
$items = [];

foreach ($announcements as $announcement) {
    if ($announcement['linkedEventID'] !== null) {
        continue;
    }

    $created = strtotime((string) $announcement['createdAt']);
    $postedBy = trim($announcement['adminFirstName'] . ' ' . $announcement['adminLastName']);

    $items[] = [
        'category' => 'Important',
        'type' => 'Announcement',
        'title' => (string) $announcement['announcementTitle'],
        'body' => (string) $announcement['announcementContent'],
        'meta' => 'Important reminder &middot; ' . attendance_datetime((string) $announcement['createdAt'])
            . ' &middot; Posted by ' . $postedBy,
        'sort' => $created ?: 0,
        'reminders' => [],
    ];
}

foreach ($events as $event) {
    $status = attendance_event_status($event);
    if ($status === 'Completed') {
        $status = 'Ended';
    }

    $details = [];
    if (!empty($event['eventDescription'])) {
        $details[] = (string) $event['eventDescription'];
    }
    $details[] = 'Date: ' . attendance_long_date($event['eventDate'])
        . ' | Time: ' . attendance_time($event['eventStartTime'])
        . (!empty($event['eventEndTime']) ? ' - ' . attendance_time($event['eventEndTime']) : '')
        . ' | Venue: ' . $event['eventLocation']
        . ' | Fee: ' . ((float) $event['participationFee'] > 0 ? attendance_money((float) $event['participationFee']) : 'Free');

    $eventTimestamp = strtotime($event['eventDate'] . ' ' . $event['eventStartTime']);
    $items[] = [
        'category' => $status,
        'type' => 'Event',
        'title' => (string) $event['eventTitle'],
        'body' => implode("\n\n", $details),
        'meta' => $status . ' event &middot; ' . attendance_long_date($event['eventDate'])
            . ' &middot; Posted by ' . trim($event['adminFirstName'] . ' ' . $event['adminLastName']),
        'sort' => $eventTimestamp ?: 0,
        'reminders' => $eventAnnouncementMap[(int) $event['eventID']] ?? [],
    ];
}

usort($items, static function (array $a, array $b) use ($sort): int {
    return $sort === 'oldest' ? $a['sort'] <=> $b['sort'] : $b['sort'] <=> $a['sort'];
});

$counts = ['all' => count($items), 'important' => 0, 'upcoming' => 0, 'ongoing' => 0, 'ended' => 0, 'cancelled' => 0];
foreach ($items as $item) {
    $key = strtolower($item['category']);
    if (isset($counts[$key])) {
        $counts[$key]++;
    }
}

$visibleItems = array_values(array_filter($items, static function (array $item) use ($filter): bool {
    return $filter === 'all' || strtolower($item['category']) === $filter;
}));

$importantReminders = array_values(array_filter($items, static function (array $item): bool {
    return $item['category'] === 'Important';
}));
$importantReminders = array_slice($importantReminders, 0, 3);

$filters = [
    'all' => 'All',
    'important' => 'Important',
    'upcoming' => 'Upcoming',
    'ongoing' => 'Ongoing',
    'ended' => 'Ended',
    'cancelled' => 'Cancelled',
];

attendance_page_start('announcements');
?>

<div class="layout">
    <section class="card">
        <div class="cardHead">
            <img src="<?= h($attendanceAssets) ?>/megaphone.png" alt="">
            <h3>Event Announcements</h3>
        </div>

        <div class="filterRow">
            <div class="filterPills">
                <?php foreach ($filters as $key => $label): ?>
                    <a class="<?= $filter === $key ? 'active' : '' ?>"
                       href="?filter=<?= h($key) ?>&amp;sort=<?= h($sort) ?>">
                        <?= h($label) ?>
                        <span class="count"><?= (int) $counts[$key] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <span class="spacer"></span>

            <form method="get" class="btnRow">
                <input type="hidden" name="filter" value="<?= h($filter) ?>">
                <select class="selectInline" name="sort" aria-label="Sort announcements">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                </select>
                <button class="btn ghost tiny" type="submit">
                    <img src="<?= h($attendanceAssets) ?>/filter.png" alt="">
                    Apply
                </button>
            </form>
        </div>

        <?php if (count($visibleItems) === 0): ?>
            <div class="emptyState">
                <img src="<?= h($attendanceAssets) ?>/megaphone.png" alt="">
                <strong>No announcements in this filter</strong>
                <p>Try another filter or check back after the next event is published.</p>
            </div>
        <?php else: ?>
            <div class="announceList">
                <?php foreach ($visibleItems as $item): ?>
                    <button class="announceItem" type="button"
                            data-announcement
                            data-title="<?= h($item['title']) ?>"
                            data-meta="<?= h(html_entity_decode($item['meta'], ENT_QUOTES, 'UTF-8')) ?>"
                            data-body="<?= h($item['body']) ?>"
                            <?php if (count($item['reminders']) > 0): ?>data-reminders="<?= h(json_encode($item['reminders'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"<?php endif; ?>>
                        <span class="aIcon">
                            <img src="<?= h($attendanceAssets) ?>/<?= $item['type'] === 'Event' ? 'calendar.png' : 'bell.png' ?>" alt="">
                        </span>
                        <span class="aText">
                            <span class="aTop">
                                <strong><?= h($item['title']) ?></strong>
                                <?= attendance_badge($item['category']) ?>
                            </span>
                            <p><?= h(mb_strimwidth($item['body'], 0, 150, '...')) ?></p>
                            <span class="aMeta"><?= $item['meta'] ?></span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="stack">
        <section class="card">
            <div class="cardHead">
                <img src="<?= h($attendanceAssets) ?>/megaphone.png" alt="">
                <h3>Announcement details</h3>
            </div>
            <div class="detailPanel">
                <h4 id="detailTitle">Select an announcement</h4>
                <div class="detailMeta" id="detailMeta">Choose an item on the left to read its full details here.</div>
                <p class="placeholder" id="detailBody">No announcement selected.</p>
            </div>
        </section>

        <section class="card">
            <div class="cardHead">
                <img src="<?= h($attendanceAssets) ?>/bell.png" alt="">
                <h3>Important reminders</h3>
            </div>

            <div class="infoBox" id="importantReminders">
                <?php if (count($importantReminders) === 0): ?>
                    <p>No urgent announcements at the moment.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($importantReminders as $reminder): ?>
                            <li><?= h($reminder['title']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="reminderHint">Select an event on the left to see its announcements.</p>
            </div>
        </section>
    </div>
</div>

<?php attendance_page_end(); ?>
