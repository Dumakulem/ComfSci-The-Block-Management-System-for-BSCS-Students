<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/icons.php';

$page_title = $PAGE_TITLE ?? 'Gamified Quiz';
$flash_msg = flash();
?>

<!DOCTYPE html>
<html lang="en" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= h($page_title) ?></title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="../../assets/sidebar.css?v=1">
</head>

<body class="comfsci-shell">
    <?php require __DIR__ . '/../../../includes/sidebar.php'; ?>
    <div class="app comfsci-app">

        <main>

            <!-- Top Header -->
            <div class="top">
                <div class="crumb">
                    <?= ic('bars') ?>
                    <span><?= h($page_title) ?></span>
                </div>

                <div>
                    <button class="ib" title="Notifications">
                        <?= ic('bell') ?>
                    </button>

                    <button class="ib" title="Settings">
                        <?= ic('gear') ?>
                    </button>
                </div>
            </div>

            <!-- Flash Message -->
            <?php if ($flash_msg): ?>
                <div style="
                    background: #e6f4ea;
                    color: #137333;
                    padding: 12px 20px;
                    border-radius: 12px;
                    margin-bottom: 14px;
                    border: 1px solid #ceead6;
                    font-weight: 600;
                ">
                    <?= h($flash_msg) ?>
                </div>
            <?php endif; ?>