<?php

$renderFields = static function (?array $record) use ($entity, $options, $adminEntity): void {
    foreach ($entity['fields'] as $field) {
        $name = $field['name'];
        $type = $field['type'];
        $fieldId = 'admin-' . $adminEntity . '-' . $name . '-' . ($record[$entity['primaryKey']] ?? 'new');
        $value = $record[$name] ?? '';
        $required = !empty($field['required']) && ($name !== 'password' || $record === null);
        $class = !empty($field['full']) ? 'admin-form-field admin-field-full' : 'admin-form-field';

        if ($type === 'datetime-local' && $value !== '') {
            $value = str_replace(' ', 'T', substr((string) $value, 0, 16));
        } elseif ($type === 'date' && $value !== '') {
            $value = substr((string) $value, 0, 10);
        } elseif ($type === 'time' && $value !== '') {
            $value = substr((string) $value, 0, 5);
        } elseif ($type === 'number' && $value === '') {
            $value = '0.00';
        }

        echo '<div class="' . $class . '">';
        if ($type === 'checkbox') {
            echo '<label class="admin-checkbox" for="' . dashboard_escape($fieldId) . '">';
            echo '<input id="' . dashboard_escape($fieldId) . '" type="checkbox" name="' . dashboard_escape($name)
                . '" value="1"' . (!empty($value) ? ' checked' : '') . '>';
            echo '<span>' . dashboard_escape($field['label']) . '</span></label>';
        } else {
            echo '<label for="' . dashboard_escape($fieldId) . '">' . dashboard_escape($field['label']) . '</label>';
            if ($type === 'textarea') {
                echo '<textarea id="' . dashboard_escape($fieldId) . '" name="' . dashboard_escape($name) . '" rows="3"'
                    . ($required ? ' required' : '') . '>' . dashboard_escape((string) $value) . '</textarea>';
            } elseif ($type === 'select') {
                $usesOptionMap = is_string($field['options']);
                $fieldOptions = $usesOptionMap ? ($options[$field['options']] ?? []) : $field['options'];
                echo '<select id="' . dashboard_escape($fieldId) . '" name="' . dashboard_escape($name) . '"'
                    . ($required ? ' required' : '') . '>';
                echo '<option value="">' . (!empty($field['required']) ? 'Choose ' . dashboard_escape(strtolower($field['label'])) : 'None') . '</option>';
                foreach ($fieldOptions as $optionValue => $optionLabel) {
                    if (!$usesOptionMap) {
                        $optionValue = $optionLabel;
                    }
                    $selected = (string) $value === (string) $optionValue ? ' selected' : '';
                    echo '<option value="' . dashboard_escape((string) $optionValue) . '"' . $selected . '>'
                        . dashboard_escape((string) $optionLabel) . '</option>';
                }
                echo '</select>';
            } else {
                echo '<input id="' . dashboard_escape($fieldId) . '" type="' . dashboard_escape($type)
                    . '" name="' . dashboard_escape($name) . '" value="' . dashboard_escape((string) $value) . '"'
                    . (isset($field['maxlength']) ? ' maxlength="' . (int) $field['maxlength'] . '"' : '')
                    . (isset($field['step']) ? ' step="' . dashboard_escape((string) $field['step']) . '"' : '')
                    . (isset($field['min']) ? ' min="' . dashboard_escape((string) $field['min']) . '"' : '')
                    . ($required ? ' required' : '')
                    . ($name === 'password' && $record !== null ? ' placeholder="Leave blank to keep current password"' : '')
                    . '>';
            }
        }
        echo '</div>';
    }
};

$formatCell = static function (string $key, $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    if ($key === 'isClassRep') {
        return (int) $value === 1 ? 'Yes' : 'No';
    }
    if (in_array($key, ['assignmentDeadline', 'createdAt'], true)) {
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? (string) $value : date('M j, Y · g:i A', $timestamp);
    }
    if ($key === 'eventDate') {
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? (string) $value : date('M j, Y', $timestamp);
    }
    if ($key === 'eventStartTime') {
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? (string) $value : date('g:i A', $timestamp);
    }

    return (string) $value;
};
?>
    <section class="card admin-management" id="<?= dashboard_escape($adminEntity) ?>">
        <div class="card-head admin-management-head">
            <div>
                <h3><?= dashboard_escape($entity['title']) ?></h3>
                <span class="admin-record-count"><?= count($entity['rows']) ?> <?= $showArchived ? 'archived' : 'active' ?></span>
            </div>
            <div class="admin-management-actions">
                <a class="link-muted" href="./admin_<?= dashboard_escape($adminEntity) ?>.php<?= $showArchived ? '' : '?archived=1' ?>">
                    <?= $showArchived ? 'View active' : 'View archived' ?>
                </a>
                <?php if (!$showArchived): ?>
                    <details class="admin-create">
                        <summary class="btn btn-solid">Add <?= dashboard_escape(rtrim($entity['title'], 's')) ?></summary>
                        <form class="admin-form" method="post" action="./admin_action.php">
                            <?= dashboard_csrf_field() ?>
                            <input type="hidden" name="entity" value="<?= dashboard_escape($adminEntity) ?>">
                            <input type="hidden" name="action" value="create">
                            <div class="admin-form-grid"><?php $renderFields(null); ?></div>
                            <button class="btn btn-solid" type="submit">Create</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$entity['rows']): ?>
            <p class="empty"><?= $showArchived ? 'No archived records.' : 'No records yet.' ?></p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr>
                        <?php foreach ($entity['columns'] as $label): ?><th scope="col"><?= dashboard_escape($label) ?></th><?php endforeach; ?>
                        <th scope="col"><?= $showArchived ? 'Restore' : 'Manage' ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($entity['rows'] as $record): ?>
                        <tr>
                            <?php foreach ($entity['columns'] as $key => $label): ?>
                                <td><?= dashboard_escape($formatCell($key, $record[$key] ?? null)) ?></td>
                            <?php endforeach; ?>
                            <td class="admin-row-actions">
                                <?php if ($showArchived): ?>
                                    <form method="post" action="./admin_action.php">
                                        <?= dashboard_csrf_field() ?>
                                        <input type="hidden" name="entity" value="<?= dashboard_escape($adminEntity) ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?= (int) $record[$entity['primaryKey']] ?>">
                                        <button class="linkbtn" type="submit">Restore</button>
                                    </form>
                                <?php else: ?>
                                    <details class="admin-row-edit">
                                        <summary class="linkbtn">Edit</summary>
                                        <form class="admin-form" method="post" action="./admin_action.php">
                                            <?= dashboard_csrf_field() ?>
                                            <input type="hidden" name="entity" value="<?= dashboard_escape($adminEntity) ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?= (int) $record[$entity['primaryKey']] ?>">
                                            <div class="admin-form-grid"><?php $renderFields($record); ?></div>
                                            <div class="admin-form-buttons"><button class="btn btn-solid" type="submit">Save changes</button></div>
                                        </form>
                                    </details>
                                    <form method="post" action="./admin_action.php" onsubmit="return confirm('Archive this record? It can be restored later.');">
                                        <?= dashboard_csrf_field() ?>
                                        <input type="hidden" name="entity" value="<?= dashboard_escape($adminEntity) ?>">
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="id" value="<?= (int) $record[$entity['primaryKey']] ?>">
                                        <button class="linkbtn admin-archive-button" type="submit">Archive</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
