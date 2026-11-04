<?php
class AnnouncementBoard
{
    public function __construct(private PDO $pdo)
    {
    }

    public static function input(array $data): array
    {
        $result = [];

        foreach (['title' => 160, 'body' => 2000] as $field => $limit) {
            $value = $data[$field] ?? null;

            if (!is_string($value) || trim($value) === '' || mb_strlen($value, 'UTF-8') > $limit) {
                throw new InvalidArgumentException('Поле ' . $field . ': потрібен текст від 1 до ' . $limit . ' символів.');
            }

            $result[$field] = trim($value);
        }

        return $result;
    }

    private function row(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['created_at'] = (new DateTimeImmutable($row['created_at']))->format(DATE_ATOM);

        return $row;
    }

    public function all(): array
    {
        $rows = $this->pdo->query('SELECT id, title, body, created_at FROM announcements ORDER BY id DESC')->fetchAll();

        return array_map(fn($row) => $this->row($row), $rows);
    }

    public function add(array $input): array
    {
        $statement = $this->pdo->prepare('INSERT INTO announcements (title, body) VALUES (:title, :body)');
        $statement->execute($input);
        $id = (int)$this->pdo->lastInsertId();
        $statement = $this->pdo->prepare('SELECT id, title, body, created_at FROM announcements WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $this->row($statement->fetch());
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM announcements WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }
}
