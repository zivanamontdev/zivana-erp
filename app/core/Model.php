<?php

abstract class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(string $orderBy = ''): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function find($id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function where(string $column, $value): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = :value");
        $stmt->execute(['value' => $value]);
        return $stmt->fetchAll();
    }

    public function whereFirst(string $column, $value): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data)
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return $this->db->lastInsertId();
    }

    public function update($id, array $data): bool
    {
        $assignments = array_map(fn($c) => "{$c} = :{$c}", array_keys($data));

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :__id',
            $this->table,
            implode(', ', $assignments),
            $this->primaryKey
        );

        $data['__id'] = $id;
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete($id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function paginate(int $page = 1, int $perPage = 20, string $orderBy = ''): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->db->query("SELECT COUNT(*) as total FROM {$this->table}");
        $total = (int) $countStmt->fetch()['total'];

        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / max(1, $perPage)),
        ];
    }
}
