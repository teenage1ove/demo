<?php

class Db
{
    private mysqli $conn;

    public function __construct(string $host, string $user, string $pass, string $name)
    {
        $this->conn = new mysqli($host, $user, $pass, $name);
        if ($this->conn->connect_errno) {
            die('Ошибка подключения к базе данных: ' . $this->conn->connect_error);
        }
        $this->conn->set_charset('utf8mb4');
    }

    private function run(string $sql, array $params): mysqli_stmt
    {
        $stmt = $this->conn->prepare($sql);
        if ($params) {
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function value(string $sql, array $params = [])
    {
        $row = $this->run($sql, $params)->get_result()->fetch_row();
        return $row ? $row[0] : null;
    }

    public function insert(string $sql, array $params = []): int
    {
        return (int) $this->run($sql, $params)->insert_id;
    }

    public function exec(string $sql, array $params = []): int
    {
        return (int) $this->run($sql, $params)->affected_rows;
    }
}
