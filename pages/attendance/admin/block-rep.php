<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard('admin');

$currentUrl = $attendanceBase . '/admin/block-rep.php';
$courseId = (int) ($_GET['course'] ?? 0);

/* ------------------------------------------------------------------
   Isang block rep kada block. Kapag nag-save ng bagong rep,
   binubura yung dating rep ng block na yun sa iisang transaction.
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    attendance_check_csrf();

    $blockId = (int) ($_POST['block_id'] ?? 0);
    $repStudentId = (int) ($_POST['rep_student_id'] ?? 0);

    try {
        $validRep = $repStudentId === 0;

        if ($repStudentId > 0) {
            $checkRep = $pdo->prepare('SELECT studentID FROM tblstudent WHERE studentID = ? AND blockID = ?');
            $checkRep->execute([$repStudentId, $blockId]);
            $validRep = (bool) $checkRep->fetchColumn();
        }

        if ($blockId <= 0 || !$validRep) {
            attendance_flash('error', 'Choose a student from that block.');
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE tblstudent SET isClassRep = 0 WHERE blockID = ?')->execute([$blockId]);
            if ($repStudentId > 0) {
                $pdo->prepare('UPDATE tblstudent SET isClassRep = 1 WHERE studentID = ?')->execute([$repStudentId]);
            }
            $pdo->commit();

            attendance_flash(
                'success',
                $repStudentId > 0
                    ? 'Block representative saved. Only one student per block can be the block rep.'
                    : 'Block representative removed from that block.'
            );
        }
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        attendance_flash('error', 'The block representative could not be saved. Please try again.');
    }

    attendance_redirect($currentUrl . '?course=' . $courseId);
}

/* Data na kailangan ng page */
$courses = $pdo->query('SELECT courseID, courseName FROM tblcourse ORDER BY courseName')->fetchAll();

$blockSql = 'SELECT b.blockID, b.classBlock, b.courseID, c.courseName
             FROM tblblock b
             LEFT JOIN tblcourse c ON c.courseID = b.courseID';
$blockParams = [];
if ($courseId > 0) {
    $blockSql .= ' WHERE b.courseID = ?';
    $blockParams[] = $courseId;
}
$blockSql .= ' ORDER BY c.courseName, b.classBlock';
$blockStatement = $pdo->prepare($blockSql);
$blockStatement->execute($blockParams);
$blocks = $blockStatement->fetchAll();

$blockRepRows = $pdo->query(
    'SELECT studentID, blockID, studentLastName, studentFirstName
     FROM tblstudent WHERE isClassRep = 1 ORDER BY studentID'
)->fetchAll();
$blockReps = [];
foreach ($blockRepRows as $blockRepRow) {
    $blockReps[(int) $blockRepRow['blockID']] ??= $blockRepRow;
}

$studentRows = $pdo->query(
    'SELECT studentID, blockID, studentLastName, studentFirstName
     FROM tblstudent ORDER BY studentLastName, studentFirstName'
)->fetchAll();
$studentsByBlock = [];
foreach ($studentRows as $studentRow) {
    $studentsByBlock[(int) $studentRow['blockID']][] = $studentRow;
}

$assignedCount = 0;
foreach ($blocks as $block) {
    if (isset($blockReps[(int) $block['blockID']])) {
        $assignedCount++;
    }
}

attendance_page_start('reps');
?>

<div class="stack">
    <div class="highlightGrid">
        <section class="highlight light">
            <div>
                <div class="eyebrow">Blocks</div>
                <strong><?= count($blocks) ?></strong>
                <small class="muted">Shown in this filter</small>
            </div>
        </section>
        <section class="highlight light">
            <div>
                <div class="eyebrow">Block reps</div>
                <strong><?= $assignedCount ?></strong>
                <small class="muted">One per block</small>
            </div>
        </section>
        <section class="highlight light">
            <div>
                <div class="eyebrow">Unassigned</div>
                <strong><?= max(0, count($blocks) - $assignedCount) ?></strong>
                <small class="muted">Blocks without a representative</small>
            </div>
            <img src="<?= h($attendanceAssets) ?>/users.png" alt="" style="width:2.2rem;height:2.2rem;object-fit:contain;opacity:0.7;">
        </section>
    </div>

    <section class="card">
        <div class="cardHead">
            <img src="<?= h($attendanceAssets) ?>/users.png" alt="">
            <h3>Block representatives</h3>
            <span class="spacer"></span>
            <span class="pillLink">One per block</span>
        </div>

        <?php if (count($blocks) === 0): ?>
            <div class="emptyState">
                <img src="<?= h($attendanceAssets) ?>/users.png" alt="">
                <strong>No blocks yet</strong>
                <p>Add a block first, then a student from that block can be assigned as block representative.</p>
            </div>
        <?php else: ?>
            <div class="cardBody">
                <!-- Filter lang ito ng table. Ang assign ay nasa Assign/Change button ng bawat row. -->
                <form method="get" class="formGrid">
                    <label class="field">
                        <span>Course</span>
                        <select name="course" onchange="this.form.submit()">
                            <option value="0">All courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= (int) $course['courseID'] ?>" <?= (int) $course['courseID'] === $courseId ? 'selected' : '' ?>>
                                    <?= h($course['courseName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <noscript>
                        <div class="formActions"><button class="btn ghost tiny" type="submit">Filter</button></div>
                    </noscript>
                </form>
            </div>
            <div class="tableWrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Block</th>
                            <th>Course</th>
                            <th>Block representative</th>
                            <th>Assign</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blocks as $block): ?>
                            <?php
                            $blockId = (int) $block['blockID'];
                            $currentRep = $blockReps[$blockId] ?? null;
                            ?>
                            <tr>
                                <td class="strong"><?= h($block['classBlock']) ?></td>
                                <td><?= h((string) ($block['courseName'] ?? '-')) ?></td>
                                <td>
                                    <?php if ($currentRep !== null): ?>
                                        <?= h(attendance_student_name($currentRep)) ?>
                                    <?php else: ?>
                                        <span class="small muted">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn ghost tiny" type="button" data-modal-open="assignRepModal<?= $blockId ?>">
                                        <?= $currentRep !== null ? 'Change' : 'Assign' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Isang modal kada block. Dito nakapasok ang students ng block na yun lang. -->
    <?php foreach ($blocks as $block): ?>
        <?php
        $blockId = (int) $block['blockID'];
        $currentRep = $blockReps[$blockId] ?? null;
        $blockStudents = $studentsByBlock[$blockId] ?? [];
        ?>
        <div class="modal" id="assignRepModal<?= $blockId ?>" hidden>
            <div class="modalBackdrop"></div>
            <div class="modalPanel">
                <div class="modalHead">
                    <div>
                        <h3>Block representative for <?= h($block['classBlock']) ?> (<?= h((string) ($block['courseName'] ?? '-')) ?>)</h3>
                        <p>Only the students of this block are listed here.</p>
                    </div>
                    <button class="closeBtn" type="button" data-modal-close aria-label="Close">
                        <img src="<?= h($attendanceAssets) ?>/close.png" alt="">
                    </button>
                </div>
                <div class="modalBody">
                    <?php if (count($blockStudents) === 0): ?>
                        <p class="hint">This block has no students yet, so there is no one to assign.</p>
                    <?php else: ?>
                        <form method="post">
                            <?= attendance_csrf_field() ?>
                            <input type="hidden" name="block_id" value="<?= $blockId ?>">
                            <!-- Isang field lang: dito mismo nag-search at pumipili ng student -->
                            <label class="field wide">
                                <span>Student</span>
                                <input type="search" data-rep-search placeholder="Search student ID or name..." autocomplete="off">
                            </label>
                            <div class="radioOptions repOptions">
                                <label class="radioOption" data-rep-option data-rep-none>
                                    <input type="radio" name="rep_student_id" value="0" <?= $currentRep === null ? 'checked' : '' ?>>
                                    <span>No block rep<small>Remove the representative from this block</small></span>
                                </label>
                                <?php foreach ($blockStudents as $studentOption): ?>
                                    <label class="radioOption" data-rep-option>
                                        <input type="radio" name="rep_student_id" value="<?= (int) $studentOption['studentID'] ?>" <?= $currentRep !== null && (int) $currentRep['studentID'] === (int) $studentOption['studentID'] ? 'checked' : '' ?>>
                                        <span><?= (int) $studentOption['studentID'] ?> - <?= h(attendance_student_name($studentOption)) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <small class="small muted" data-rep-empty hidden>No student matched your search.</small>
                            <div class="formActions">
                                <button class="btn" type="submit">Save block rep</button>
                                <button class="btn ghost" type="button" data-modal-close>Cancel</button>
                                <span class="small muted">Current: <?= $currentRep !== null ? h(attendance_student_name($currentRep)) : 'Unassigned' ?></span>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
    (function () {
        // Iisang listahan: dito nag-search at dito rin pumipili ng student
        function setupRepSearch(modal) {
            const form = modal.querySelector('form');
            if (!form) return;

            const input = form.querySelector('[data-rep-search]');
            const empty = form.querySelector('[data-rep-empty]');
            const saveButton = form.querySelector('button[type="submit"]');
            if (!input) return;

            const options = Array.prototype.slice.call(form.querySelectorAll('[data-rep-option]'));

            function applyFilter() {
                const term = input.value.trim().toLowerCase();
                let matches = 0;

                options.forEach(function (option) {
                    const isNone = option.hasAttribute('data-rep-none');
                    // Kapag may hinahanap, tinatago muna ang "No block rep"
                    const show = term === ''
                        ? true
                        : (!isNone && option.textContent.toLowerCase().indexOf(term) !== -1);

                    option.hidden = !show;
                    if (show && !isNone) matches++;
                });

                if (empty) empty.hidden = !(term !== '' && matches === 0);
                // Walang tugma = walang pwedeng i-save
                if (saveButton) saveButton.disabled = term !== '' && matches === 0;
            }

            input.addEventListener('input', applyFilter);
        }

        document.querySelectorAll('.modal[id^="assignRepModal"]').forEach(setupRepSearch);

        // Kada bukas ng modal, malinis na search at buong listahan ulit ang lalabas
        document.addEventListener('click', function (event) {
            const opener = event.target.closest('[data-modal-open]');
            if (!opener) return;

            const modal = document.getElementById(opener.getAttribute('data-modal-open'));
            if (!modal) return;

            const input = modal.querySelector('[data-rep-search]');
            if (input) {
                input.value = '';
                input.dispatchEvent(new Event('input'));
            }
        });
    })();
    </script>

    <section class="card">
        <div class="infoBox">
            <h4><img src="<?= h($attendanceAssets) ?>/info.png" alt=""> How block representatives work</h4>
            <ul>
                <li>Click the Assign/Change button on a block row. Only the students of that block appear in the list. You can also search by student ID or name in the filter inside the modal, then save the block rep.</li>
                <li>Only one block representative is allowed per block. Saving a new one clears the previous rep.</li>
                <li>Choosing No block rep removes the representative from that block.</li>
                <li>A block rep sees the same categories as a student, plus attendance marking and payment approval for their own block only.</li>
                <li>Block reps can approve cash payments and verify online payments of students in their block.</li>
            </ul>
        </div>
    </section>
</div>

<?php attendance_page_end(); ?>
