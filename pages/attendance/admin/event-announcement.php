<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard('admin');

$currentUrl = $attendanceBase . '/admin/event-announcement.php';
$eventFilter = (int) ($_GET['event'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    attendance_check_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $announcementId = (int) ($_POST['announcement_id'] ?? 0);
    $linkedEventId = (int) ($_POST['event_id'] ?? 0);

    try {
        if ($action === 'create') {
            $title = trim((string) ($_POST['announcement_title'] ?? ''));
            $content = trim((string) ($_POST['announcement_content'] ?? ''));
            $validEventId = null;

            if ($linkedEventId > 0) {
                $eventCheck = $pdo->prepare('SELECT 1 FROM tblevent WHERE eventID = ?');
                $eventCheck->execute([$linkedEventId]);
                if (!$eventCheck->fetchColumn()) {
                    attendance_flash('error', 'The selected event could not be found.');
                    attendance_redirect($currentUrl);
                }
                $validEventId = $linkedEventId;
            }

            if ($title === '' || $content === '') {
                attendance_flash('error', 'Title and content are required.');
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO tblannouncement (adminID, eventID, announcementTitle, announcementContent)
                     VALUES (?, ?, ?, ?)'
                );
                $insert->execute([$viewerId, $validEventId, $title, $content]);
                attendance_flash('success', $validEventId !== null
                    ? 'Announcement published for the selected event.'
                    : 'General announcement published.');
            }
        } elseif ($action === 'delete') {
            $delete = $pdo->prepare('DELETE FROM tblannouncement WHERE announcementID = ?');
            $delete->execute([$announcementId]);
            attendance_flash('success', 'Announcement deleted.');
        } else {
            attendance_flash('error', 'Unknown action.');
        }
    } catch (PDOException $exception) {
        attendance_flash('error', 'The announcement could not be saved. Please try again.');
    }

    attendance_redirect($currentUrl . ($eventFilter > 0 ? '?event=' . $eventFilter : ''));
}

/* Data na kailangan ng page */
// Active events: walang archived at walang cancelled (para sa 'Latest events' at selections)
$activeEvents = $pdo->query(
    'SELECT e.*, c.courseName
     FROM tblevent e
     LEFT JOIN tblcourse c ON c.courseID = e.courseID
     WHERE e.archivedAt IS NULL AND e.eventStatus <> \'Cancelled\'
     ORDER BY e.eventDate DESC, e.eventStartTime DESC'
)->fetchAll();

// Selectable events para sa filter/publish: walang cancelled at walang deleted (archived)
$selectableEvents = $pdo->query(
    'SELECT eventID, eventTitle, eventDate, eventStatus
     FROM tblevent
     WHERE archivedAt IS NULL AND eventStatus <> \'Cancelled\'
     ORDER BY eventDate DESC, eventStartTime DESC'
)->fetchAll();

$allEvents = $pdo->query(
    'SELECT eventID, eventTitle, eventDate, eventStatus, archivedAt
     FROM tblevent
     ORDER BY eventDate DESC, eventStartTime DESC'
)->fetchAll();

if ($eventFilter > 0) {
    $announcementStatement = $pdo->prepare(
        'SELECT an.*, a.adminFirstName, a.adminLastName,
                e.eventTitle, e.eventDate, e.eventStatus, e.archivedAt
         FROM tblannouncement an
         JOIN tbladmin a ON a.adminID = an.adminID
         LEFT JOIN tblevent e ON e.eventID = an.eventID
         WHERE an.eventID = ?
         ORDER BY an.createdAt DESC'
    );
    $announcementStatement->execute([$eventFilter]);
    $announcements = $announcementStatement->fetchAll();
} else {
    $announcements = $pdo->query(
        'SELECT an.*, a.adminFirstName, a.adminLastName,
                e.eventTitle, e.eventDate, e.eventStatus, e.archivedAt
         FROM tblannouncement an
         JOIN tbladmin a ON a.adminID = an.adminID
         LEFT JOIN tblevent e ON e.eventID = an.eventID
         ORDER BY an.createdAt DESC'
    )->fetchAll();
}

$filteredEvent = null;
foreach ($allEvents as $event) {
    if ((int) $event['eventID'] === $eventFilter) {
        $filteredEvent = $event;
        break;
    }
}

attendance_page_start('announcements');
?>

<div class="layout">
    <div class="stack">
        <section class="card">
            <div class="cardHead">
                <img src="<?= h($attendanceAssets) ?>/megaphone.png" alt="">
                <h3>Publish an announcement</h3>
            </div>
            <div class="cardBody">
                <form method="post">
                    <?= attendance_csrf_field() ?>
                    <input type="hidden" name="action" value="create">

                    <div class="formGrid">
                        <label class="field wide">
                            <span>Announcement title</span>
                            <input type="text" name="announcement_title" maxlength="150" placeholder="e.g. Venue change for the Sports Fest" required>
                        </label>
                        <label class="field wide">
                            <span>Event</span>
                            <input type="hidden" name="event_id" id="publishEventId" value="<?= (int) $eventFilter ?>">
                            <div class="searchSelect" data-hidden="publishEventId">
                                <input type="text" class="searchSelectInput" placeholder="General announcement or search event..." autocomplete="off"
                                    value="<?= $eventFilter > 0 ? h((function() use ($selectableEvents, $eventFilter) { foreach ($selectableEvents as $e) if ((int)$e['eventID']===$eventFilter) return $e['eventTitle'].' — '.attendance_long_date($e['eventDate']); return 'General announcement (Important reminder)'; })()) : 'General announcement (Important reminder)' ?>">
                                <ul class="searchSelectList" hidden>
                                    <li data-value="0">General announcement (Important reminder)</li>
                                    <?php foreach ($selectableEvents as $event): ?>
                                        <li data-value="<?= (int) $event['eventID'] ?>"><?= h($event['eventTitle']) ?> &mdash; <?= h(attendance_long_date($event['eventDate'])) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <span class="hint">Choosing an event connects this announcement to the Campus Events page and follows the event's status.</span>
                        </label>
                        <label class="field wide">
                            <span>Content</span>
                            <textarea name="announcement_content" placeholder="Write the details students need to know." required></textarea>
                        </label>
                    </div>

                    <div class="formActions">
                        <button class="btn" type="submit">
                            <img src="<?= h($attendanceAssets) ?>/megaphone.png" alt="">
                            Publish announcement
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="cardHead">
                <img src="<?= h($attendanceAssets) ?>/bell.png" alt="">
                <h3><?= $filteredEvent !== null ? h($filteredEvent['eventTitle']) . ' announcements' : 'Published announcements' ?></h3>
                <span class="spacer"></span>
                <span class="pillLink"><?= count($announcements) ?> total</span>
            </div>

            <div class="filterRow">
                <form method="get" class="btnRow">
                    <input type="hidden" name="event" id="filterEventId" value="<?= (int) $eventFilter ?>">
                    <div class="searchSelect" data-hidden="filterEventId">
                        <input type="text" class="searchSelectInput" placeholder="All announcements or search event..." autocomplete="off"
                            value="<?= $eventFilter > 0 ? h((function() use ($selectableEvents, $eventFilter) { foreach ($selectableEvents as $e) if ((int)$e['eventID']===$eventFilter) return $e['eventTitle']; return 'All announcements'; })()) : 'All announcements' ?>">
                        <ul class="searchSelectList" hidden>
                            <li data-value="0">All announcements</li>
                            <?php foreach ($selectableEvents as $event): ?>
                                <li data-value="<?= (int) $event['eventID'] ?>"><?= h($event['eventTitle']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <button class="btn ghost tiny" type="submit">
                        <img src="<?= h($attendanceAssets) ?>/filter.png" alt="">
                        Apply
                    </button>
                    <?php if ($eventFilter > 0): ?>
                        <a class="btn gray tiny" href="<?= h($currentUrl) ?>">Show all</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (count($announcements) === 0): ?>
                <div class="emptyState">
                    <img src="<?= h($attendanceAssets) ?>/megaphone.png" alt="">
                    <strong>No announcements yet</strong>
                    <p>Published announcements appear on the student Event Announcements page.</p>
                </div>
            <?php else: ?>
                <div class="announceList">
                    <?php foreach ($announcements as $announcement): ?>
                        <?php
                        $isGeneral = $announcement['eventID'] === null;
                        $category = 'Important';
                        if (!$isGeneral) {
                            $category = attendance_event_status([
                                'eventStatus' => (string) $announcement['eventStatus'],
                                'eventDate' => (string) $announcement['eventDate'],
                            ]);
                            if (in_array($category, ['Completed'], true)) {
                                $category = 'Ended';
                            }
                        }
                        ?>
                        <div class="announceItem" style="cursor:default;">
                            <span class="aIcon">
                                <img src="<?= h($attendanceAssets) ?>/<?= $isGeneral ? 'bell.png' : 'megaphone.png' ?>" alt="">
                            </span>
                            <span class="aText">
                                <span class="aTop">
                                    <strong><?= h($announcement['announcementTitle']) ?></strong>
                                    <?= attendance_badge($isGeneral ? 'Important' : $category) ?>
                                    <?php if ($announcement['archivedAt'] !== null): ?>
                                        <span class="badge">Archived event</span>
                                    <?php endif; ?>
                                </span>
                                <p><?= h(mb_strimwidth((string) $announcement['announcementContent'], 0, 180, '...')) ?></p>
                                <span class="aMeta">
                                    <?= $isGeneral ? 'General announcement' : 'Event: ' . h((string) $announcement['eventTitle']) ?>
                                    &middot; Posted by <?= h(trim($announcement['adminFirstName'] . ' ' . $announcement['adminLastName'])) ?>
                                    &middot; <?= h(attendance_datetime((string) $announcement['createdAt'])) ?>
                                </span>
                            </span>
                            <form method="post" style="margin:0;align-self:center;" onsubmit="return confirm('Delete this announcement?');">
                                <?= attendance_csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="announcement_id" value="<?= (int) $announcement['announcementID'] ?>">
                                <button class="btn red tiny" type="submit">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="cardHead">
                <img src="<?= h($attendanceAssets) ?>/calendar.png" alt="">
                <h3>Latest events</h3>
            </div>

            <?php if (count($activeEvents) === 0): ?>
                <div class="infoBox">
                    <p>No active events. Create one from the Campus Events tab.</p>
                </div>
            <?php else: ?>
                <div class="filterRow" style="margin-bottom:.5rem;">
                    <input type="search" id="latestEventSearch" placeholder="Search event..." aria-label="Search latest events">
                    <select id="latestEventStatus" aria-label="Filter by status">
                        <option value="all">All statuses</option>
                        <option value="Upcoming">Upcoming</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Ended">Ended</option>
                    </select>
                </div>
                <div class="tableWrap" style="max-height:16rem;overflow-y:auto;">
                    <table class="table" id="latestEventsTable">
                        <tbody>
                            <?php foreach ($activeEvents as $event): ?>
                                <tr data-title="<?= h(strtolower($event['eventTitle'])) ?>" data-status="<?= h(attendance_event_status($event)) ?>">
                                    <td class="strong">
                                        <?= h($event['eventTitle']) ?>
                                        <span class="sub"><?= h(attendance_long_date($event['eventDate'])) ?></span>
                                    </td>
                                    <td class="nowrap"><?= attendance_badge(attendance_event_status($event)) ?></td>
                                    <td class="nowrap">
                                        <button class="btn ghost tiny" type="button" data-modal-open="viewEventModal<?= (int) $event['eventID'] ?>">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="infoBox">
                <h4><img src="<?= h($attendanceAssets) ?>/info.png" alt=""> How announcements connect</h4>
                <ul>
                    <li>An announcement linked to an event also appears inside that event's details on the student Campus Events page.</li>
                    <li>It follows the event status, so students see it under Upcoming, Ongoing or Ended.</li>
                    <li>General announcements stay under Important reminders.</li>
                    <li>Archiving an event hides its announcements from students until you restore it.</li>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php foreach ($activeEvents as $event): ?>
    <?php $viewStatus = attendance_event_status($event); ?>
    <div class="modal" id="viewEventModal<?= (int) $event['eventID'] ?>" hidden>
        <div class="modalBackdrop"></div>
        <div class="modalPanel">
            <div class="modalHead">
                <div>
                    <h3><?= h($event['eventTitle']) ?></h3>
                    <p><?= h($viewStatus) ?> event</p>
                </div>
                <button class="closeBtn" type="button" data-modal-close aria-label="Close">
                    <img src="<?= h($attendanceAssets) ?>/close.png" alt="">
                </button>
            </div>
            <div class="modalBody">
                <p class="detailDescription"><?= h((string) ($event['eventDescription'] ?? 'No description provided.')) ?></p>

                <div class="detailGrid">
                    <div class="field"><span>Date</span><div class="value"><?= h(attendance_long_date($event['eventDate'])) ?></div></div>
                    <div class="field"><span>Time</span><div class="value"><?= h(attendance_time($event['eventStartTime'])) ?><?= !empty($event['eventEndTime']) ? ' - ' . h(attendance_time($event['eventEndTime'])) : '' ?></div></div>
                    <div class="field"><span>Location</span><div class="value"><?= h($event['eventLocation']) ?></div></div>
                    <div class="field"><span>Participation fee</span><div class="value"><?= (float) $event['participationFee'] > 0 ? attendance_money((float) $event['participationFee']) : 'Free' ?></div></div>
                    <div class="field"><span>Open to</span><div class="value"><?= h(attendance_event_audience($event)) ?></div></div>
                    <?php if ((float) $event['participationFee'] > 0): ?>
                        <div class="field"><span>Payment deadline</span><div class="value"><?= !empty($event['paymentDeadline']) ? h(attendance_long_date((string) $event['paymentDeadline'])) : 'No deadline' ?></div></div>
                    <?php endif; ?>
                    <div class="field"><span>Status</span><div class="value"><?= h($viewStatus) ?></div></div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
/* search/filter sa latest events table */
var latestSearch = document.getElementById('latestEventSearch');
var latestStatus = document.getElementById('latestEventStatus');
var latestTable = document.getElementById('latestEventsTable');
function filterLatestEvents() {
    if (!latestTable) return;
    var q = latestSearch ? latestSearch.value.trim().toLowerCase() : '';
    var st = latestStatus ? latestStatus.value : 'all';
    Array.from(latestTable.querySelectorAll('tbody tr')).forEach(function (row) {
        var title = row.getAttribute('data-title') || '';
        var status = row.getAttribute('data-status') || '';
        var show = (q === '' || title.indexOf(q) !== -1) && (st === 'all' || status === st);
        row.style.display = show ? '' : 'none';
    });
}
if (latestSearch) latestSearch.addEventListener('input', filterLatestEvents);
if (latestStatus) latestStatus.addEventListener('change', filterLatestEvents);

/* searchable select (select + search sa iisang control) */
document.querySelectorAll('.searchSelect').forEach(function (wrap) {
    var hiddenId = wrap.getAttribute('data-hidden');
    var hidden = document.getElementById(hiddenId);
    var input = wrap.querySelector('.searchSelectInput');
    var list = wrap.querySelector('.searchSelectList');
    if (!input || !list || !hidden) return;

    function showList(q) {
        q = (q || '').trim().toLowerCase();
        var any = false;
        list.querySelectorAll('li').forEach(function (li) {
            var match = q === '' || li.textContent.toLowerCase().indexOf(q) !== -1;
            li.style.display = match ? '' : 'none';
            if (match) any = true;
        });
        list.hidden = !any;
    }
    input.addEventListener('focus', function () { showList(''); list.hidden = false; });
    input.addEventListener('input', function () { showList(input.value); list.hidden = false; });
    list.querySelectorAll('li').forEach(function (li) {
        li.addEventListener('mousedown', function (e) {
            e.preventDefault();
            input.value = li.textContent;
            hidden.value = li.getAttribute('data-value');
            list.hidden = true;
        });
    });
    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) list.hidden = true;
    });
});
</script>

<?php attendance_page_end(); ?>
