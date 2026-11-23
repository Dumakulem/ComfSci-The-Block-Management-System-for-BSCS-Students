<?php foreach($students as $student): ?>

            <?php if($student['as_status'] == "Completed"): ?>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">U</span>
              <span class="student-info">
                <form method="POST" class="student-form">
                    <button type="submit" name="viewStudent" value="<?= (int) $student['studentID'] ?>" class="student-name">
                    <?= getStudentName($pdo, $classCode, $student['studentID']) ?>
                    </button>
                </form>
                <span class="student-time">Submitted <?= (new DateTime($student['edited_at']))->format('M j, Y, g:i A') ?></span>
              </span>
              <?php if(isAdminHasFeedback($pdo, $student['sa_id']) != false): ?>
                <?php $stat =  "Graded"?>
                <?php else: ?>
                    <?php $stat =  "Completed On Time"?>
              <?php endif; ?>
              <span class="status status-ontime">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                <?= $stat ?>
              </span>
            </li>
          
            <?php endif; ?>
            
            <?php if($student['as_status'] == "Late"): ?>
            <li class="student">
              <span class="student-avatar" aria-hidden="true">U</span>
              <span class="student-info">
                <form method="POST" class="student-form">
                    <button type="submit" name="viewStudent" value="<?= (int) $student['studentID'] ?>" class="student-name">
                    <?= getStudentName($pdo, $classCode, $student['studentID']) ?>
                    </button>
                </form>
                <span class="student-time">Submitted <?= (new DateTime($student['edited_at']))->format('M j, Y, g:i A') ?></span>
              </span>
              <?php if(isAdminHasFeedback($pdo, $student['sa_id']) != false): ?>
                <?php $stat =  "Graded"?>
                <?php else: ?>
                    <?php $stat = "Late Submission"?>
              <?php endif; ?>
              <span class="status status-late">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= $stat ?>
              </span>
            </li>
    
            <?php endif; ?>

            <?php endforeach; ?>