<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Model
{
    protected string $table = '';
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    public function find(int $id): ?array
    {
        if ($this->table === '') {
            return null;
        }
        return Database::fetch("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1", [$id]);
    }

    public function insert(array $data): int
    {
        if ($this->table === '') {
            return 0;
        }
        $cols = array_keys($data);
        $sql  = "INSERT INTO {$this->table} (`" . implode('`,`', $cols) . '`) VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')';
        Database::run($sql, array_values($data));
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if ($this->table === '' || $data === []) {
            return false;
        }
        $sets = implode(',', array_map(static fn($c) => "`$c` = ?", array_keys($data)));
        Database::run("UPDATE {$this->table} SET $sets WHERE id = ?", [...array_values($data), $id]);
        return true;
    }
}
