<section class="hero">
  <h1>Hi, <?= e($student['name']) ?>! 👋</h1>
  <h2>Check out your Assignment!</h2>
  <p>You have <?= plural(count($assignments), 'assignment') ?>, <?= plural($tasksLeft, 'task') ?> left today and <?= plural(count($upcoming), 'online class', 'online classes') ?> coming up.</p>
</section>
