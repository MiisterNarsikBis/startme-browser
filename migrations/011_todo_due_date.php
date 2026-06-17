<?php
// Migration 011 — Ajout de due_date aux todos

$cols = array_column(db()->query("SHOW COLUMNS FROM todos")->fetchAll(), 'Field');
if (!in_array('due_date', $cols)) {
    db()->exec("ALTER TABLE todos ADD COLUMN due_date DATE NULL");
}
