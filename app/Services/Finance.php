<?php
declare(strict_types=1);
namespace App\Services;

use App\ValidationException;
use Mini\Database as DB;

final class Finance
{
    public const MAX_AMOUNT = 999999999999;
    public const ENTITIES = ['businesses', 'books', 'categories', 'people'];

    public static function fail(string $field, string $message): never
    {
        throw new ValidationException([$field => $message]);
    }

    public static function text(array $data, string $key, int $max = 100, bool $required = true): ?string
    {
        $value = $data[$key] ?? '';
        if (!is_scalar($value)) self::fail($key, 'Please enter a valid value.');
        $value = trim((string) $value);
        if ($required && $value === '') self::fail($key, 'This field is required.');
        if (mb_strlen($value) > $max) self::fail($key, 'This value is too long.');
        return $value === '' ? null : $value;
    }

    public static function amount(array $data, string $key = 'amount', bool $zero = false): int
    {
        $value = $data[$key] ?? '';
        if (!is_scalar($value) || !preg_match('/^\d{1,12}$/D', (string) $value) || (int) $value > self::MAX_AMOUNT || (int) $value < ($zero ? 0 : 1)) {
            self::fail($key, $zero ? 'Enter a whole MMK amount of zero or more (up to 999,999,999,999).' : 'Enter a positive whole MMK amount (up to 999,999,999,999).');
        }
        return (int) $value;
    }

    public static function date(array $data, string $key, bool $required = true): ?string
    {
        $value = self::text($data, $key, 10, $required);
        if ($value === null) return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value < '1900-01-01' || $value > '9999-12-31') self::fail($key, 'Enter a valid date.');
        return $value;
    }

    public static function choice(array $data, string $key, array $options): string
    {
        $value = self::text($data, $key);
        if (!in_array($value, $options, true)) self::fail($key, 'Please select a valid option.');
        return $value;
    }

    public static function reference(array $data, string $key, string $table, bool $required = true): ?int
    {
        $value = $data[$key] ?? '';
        if (!$required && ($value === '' || $value === null)) return null;
        if (!is_scalar($value) || !ctype_digit((string) $value) || !DB::fetchValue("SELECT id FROM `$table` WHERE id = ?", [$value])) self::fail($key, 'Please select an existing record.');
        return (int) $value;
    }

    public static function find(string $table, int $id): array
    {
        $allowed = [...self::ENTITIES, 'debts', 'transactions'];
        if (!in_array($table, $allowed, true)) throw new \InvalidArgumentException('Invalid table');
        $row = DB::fetch("SELECT * FROM `$table` WHERE id = ?", [$id]);
        if (!$row) { http_response_code(404); view('errors/404'); exit; }
        return $row;
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::ENTITIES as $table) $options[$table] = DB::fetchAll("SELECT * FROM `$table` ORDER BY name, id");
        return $options;
    }

    private static function category(int $id, string $kind): void
    {
        $category = self::find('categories', $id);
        if (!in_array($category['kind'], [$kind, 'both'], true)) self::fail('category_id', 'Choose a category matching the transaction type.');
    }

    public static function saveEntity(string $table, array $data, ?int $id = null): int
    {
        if (!in_array($table, self::ENTITIES, true)) throw new \InvalidArgumentException('Invalid table');
        $row = ['name' => self::text($data, 'name'), 'notes' => self::text($data, 'notes', 1000, false)];
        if ($table !== 'people' && DB::fetchValue("SELECT id FROM `$table` WHERE name = ? AND id <> ?", [$row['name'], $id ?? 0])) self::fail('name', 'This name is already in use.');
        if ($table === 'businesses') $row['kind'] = self::choice($data, 'kind', ['business', 'personal']);
        if ($table === 'categories') {
            $row['kind'] = self::choice($data, 'kind', ['income', 'expense', 'both']);
            if ($id && $row['kind'] !== 'both') {
                $conflicts = DB::fetchValue('SELECT COUNT(*) FROM transactions WHERE category_id = ? AND kind <> ?', [$id, $row['kind']]);
                $direction = $row['kind'] === 'income' ? 'receivable' : 'payable';
                $conflicts += DB::fetchValue('SELECT COUNT(*) FROM debts WHERE category_id = ? AND direction <> ?', [$id, $direction]);
                if ($conflicts) self::fail('kind', 'Existing records use this category for another type.');
            }
        }
        if ($table === 'books') $row['opening_balance'] = self::amount($data, 'opening_balance', true);
        if ($table === 'people') $row['phone'] = self::text($data, 'phone', 50, false);
        if ($id) { self::find($table, $id); DB::update($table, $id, $row); return $id; }
        return DB::insert($table, $row);
    }

    public static function saveTransaction(array $data, ?int $id = null): int
    {
        if ($id && self::find('transactions', $id)['debt_id']) self::fail('form', 'Undo this payment from its balance detail, then record it again.');
        $kind = self::choice($data, 'kind', ['income', 'expense', 'transfer']);
        $row = [
            'kind' => $kind, 'amount' => self::amount($data), 'record_date' => self::date($data, 'record_date'),
            'book_id' => self::reference($data, 'book_id', 'books'), 'notes' => self::text($data, 'notes', 1000, false),
            'business_id' => null, 'category_id' => null, 'to_book_id' => null, 'person_id' => null,
        ];
        if ($kind === 'transfer') {
            $row['to_book_id'] = self::reference($data, 'to_book_id', 'books');
            if ($row['book_id'] === $row['to_book_id']) self::fail('to_book_id', 'Choose a different destination account book.');
        } else {
            $row['business_id'] = self::reference($data, 'business_id', 'businesses');
            $row['category_id'] = self::reference($data, 'category_id', 'categories');
            $row['person_id'] = self::reference($data, 'person_id', 'people', false);
            self::category($row['category_id'], $kind);
        }
        if ($id) { DB::update('transactions', $id, $row); return $id; }
        return DB::insert('transactions', $row);
    }

    public static function saveDebt(array $data, ?int $id = null): int
    {
        $row = [
            'direction' => self::choice($data, 'direction', ['receivable', 'payable']),
            'business_id' => self::reference($data, 'business_id', 'businesses'),
            'person_id' => self::reference($data, 'person_id', 'people'),
            'category_id' => self::reference($data, 'category_id', 'categories'),
            'amount' => self::amount($data), 'record_date' => self::date($data, 'record_date'),
            'due_date' => self::date($data, 'due_date', false), 'notes' => self::text($data, 'notes', 1000, false),
        ];
        if ($row['due_date'] && $row['due_date'] < $row['record_date']) self::fail('due_date', 'Due date must be on or after the record date.');
        self::category($row['category_id'], $row['direction'] === 'receivable' ? 'income' : 'expense');
        return DB::transaction(function () use ($row, $id) {
            if (!$id) return DB::insert('debts', $row);
            $existing = DB::fetch('SELECT * FROM debts WHERE id = ? FOR UPDATE', [$id]);
            if (!$existing) self::fail('form', 'Record not found.');
            $paid = (int) DB::fetchValue('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE debt_id = ?', [$id]);
            if ($paid) {
                foreach (['direction', 'business_id', 'person_id', 'category_id'] as $key) {
                    if ((string) $row[$key] !== (string) $existing[$key]) self::fail($key, 'Undo existing payments before changing this field.');
                }
                if ($row['amount'] < $paid) self::fail('amount', 'Amount cannot be less than payments already recorded.');
                $first = DB::fetchValue('SELECT MIN(record_date) FROM transactions WHERE debt_id = ?', [$id]);
                if ($row['record_date'] > $first) self::fail('record_date', 'Record date cannot be after an existing payment.');
            }
            DB::update('debts', $id, $row);
            return $id;
        });
    }

    public static function settle(int $id, array $data): int
    {
        $amount = self::amount($data);
        $book = self::reference($data, 'book_id', 'books');
        $date = self::date($data, 'record_date');
        $notes = self::text($data, 'notes', 1000, false);
        return DB::transaction(function () use ($id, $amount, $book, $date, $notes) {
            $debt = DB::fetch('SELECT * FROM debts WHERE id = ? FOR UPDATE', [$id]);
            if (!$debt) self::fail('form', 'Record not found.');
            $paid = (int) DB::fetchValue('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE debt_id = ?', [$id]);
            if ($amount > (int) $debt['amount'] - $paid) self::fail('amount', 'Payment exceeds the outstanding balance.');
            if ($date < $debt['record_date']) self::fail('record_date', 'Payment date must be on or after the record date.');
            return DB::insert('transactions', [
                'debt_id' => $id, 'kind' => $debt['direction'] === 'receivable' ? 'income' : 'expense',
                'business_id' => $debt['business_id'], 'category_id' => $debt['category_id'], 'person_id' => $debt['person_id'],
                'book_id' => $book, 'amount' => $amount, 'record_date' => $date, 'notes' => $notes,
            ]);
        });
    }

    public static function delete(string $table, int $id): void
    {
        self::find($table, $id);
        if ($table === 'transactions') {
            DB::transaction(function () use ($id) {
                $row = DB::fetch('SELECT debt_id FROM transactions WHERE id = ?', [$id]);
                if ($row && $row['debt_id']) DB::statement('SELECT id FROM debts WHERE id = ? FOR UPDATE', [$row['debt_id']]);
                DB::statement('DELETE FROM transactions WHERE id = ?', [$id]);
            });
            return;
        }
        try { DB::statement("DELETE FROM `$table` WHERE id = ?", [$id]); }
        catch (\PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            self::fail('form', 'This record is in use. Remove its linked records first.');
        }
    }

    public static function books(?string $asOf = null): array
    {
        $asOf ??= date('Y-m-d');
        return DB::fetchAll("SELECT b.*, b.opening_balance + COALESCE((SELECT SUM(CASE WHEN t.kind = 'income' THEN t.amount ELSE -t.amount END) FROM transactions t WHERE t.book_id = b.id AND t.record_date <= ?),0) + COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.to_book_id = b.id AND t.record_date <= ?),0) AS balance FROM books b ORDER BY b.name", [$asOf, $asOf]);
    }

    public static function filters(bool $dates = true): array
    {
        $filters = ['business_id' => query('business_id'), 'book_id' => query('book_id'), 'category_id' => query('category_id'), 'kind' => query('kind'), 'q' => mb_substr(query('q'), 0, 100)];
        foreach (['business_id', 'book_id', 'category_id'] as $key) if ($filters[$key] && !ctype_digit($filters[$key])) self::fail('form', 'Please select a valid option.');
        if ($filters['kind'] && !in_array($filters['kind'], ['income','expense','transfer'], true)) self::fail('form', 'Please select a valid option.');
        if ($dates) {
            $filters['from'] = self::date(['from' => query('from', date('Y-m-01'))], 'from');
            $filters['to'] = self::date(['to' => query('to', date('Y-m-d'))], 'to');
            if ($filters['from'] > $filters['to']) self::fail('form', 'Start date must be on or before end date.');
        }
        return $filters;
    }

    public static function where(array $filters): array
    {
        $where = ['1=1']; $args = [];
        foreach (['business_id','category_id','kind'] as $key) if (!empty($filters[$key])) { $where[] = "t.$key = ?"; $args[] = $filters[$key]; }
        if (!empty($filters['book_id'])) { $where[] = '(t.book_id = ? OR t.to_book_id = ?)'; $args[] = $filters['book_id']; $args[] = $filters['book_id']; }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) if (!empty($filters[$key])) { $where[] = "t.record_date $operator ?"; $args[] = $filters[$key]; }
        if (!empty($filters['q'])) { $where[] = '(t.notes LIKE ? OR p.name LIKE ?)'; $args[] = '%' . $filters['q'] . '%'; $args[] = '%' . $filters['q'] . '%'; }
        return [implode(' AND ', $where), $args];
    }

    public static function transactions(array $filters, int $page = 1, int $limit = 25): array
    {
        [$where, $args] = self::where($filters);
        $joins = ' FROM transactions t LEFT JOIN businesses b ON b.id=t.business_id JOIN books a ON a.id=t.book_id LEFT JOIN books z ON z.id=t.to_book_id LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN people p ON p.id=t.person_id';
        $total = (int) DB::fetchValue('SELECT COUNT(*)' . $joins . ' WHERE ' . $where, $args);
        $pages = max(1, (int) ceil($total / $limit)); $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $limit;
        $rows = DB::fetchAll('SELECT t.*, b.name AS business_name, a.name AS book_name, z.name AS to_book_name, c.name AS category_name, p.name AS person_name' . $joins . ' WHERE ' . $where . " ORDER BY t.record_date DESC, t.id DESC LIMIT $limit OFFSET $offset", $args);
        return compact('rows','total','page','pages');
    }

    public static function report(array $filters): array
    {
        [$where, $args] = self::where($filters);
        $join = ' FROM transactions t LEFT JOIN people p ON p.id = t.person_id';
        $totals = DB::fetch("SELECT COALESCE(SUM(CASE WHEN t.kind='income' THEN t.amount ELSE 0 END),0) income, COALESCE(SUM(CASE WHEN t.kind='expense' THEN t.amount ELSE 0 END),0) expense, COUNT(*) count" . $join . ' WHERE ' . $where, $args);
        $totals['net'] = (int) $totals['income'] - (int) $totals['expense'];
        $months = DB::fetchAll("SELECT DATE_FORMAT(t.record_date,'%Y-%m') month, SUM(CASE WHEN t.kind='income' THEN t.amount ELSE 0 END) income, SUM(CASE WHEN t.kind='expense' THEN t.amount ELSE 0 END) expense" . $join . ' WHERE ' . $where . ' GROUP BY month ORDER BY month', $args);
        $categories = DB::fetchAll("SELECT c.name, t.kind, SUM(t.amount) amount" . $join . " JOIN categories c ON c.id=t.category_id WHERE $where AND t.kind <> 'transfer' GROUP BY c.id,c.name,t.kind ORDER BY amount DESC", $args);
        $businesses = DB::fetchAll("SELECT b.name, b.kind, SUM(CASE WHEN t.kind='income' THEN t.amount ELSE 0 END) income, SUM(CASE WHEN t.kind='expense' THEN t.amount ELSE 0 END) expense" . $join . " JOIN businesses b ON b.id=t.business_id WHERE $where GROUP BY b.id,b.name,b.kind ORDER BY income DESC", $args);
        return compact('totals','months','categories','businesses');
    }

    public static function debts(array $filters = []): array
    {
        $where = ['1=1']; $args = [];
        foreach (['business_id','person_id','direction','id'] as $key) if (!empty($filters[$key])) { $where[] = "d.$key = ?"; $args[] = $filters[$key]; }
        if (!empty($filters['q'])) { $where[] = '(p.name LIKE ? OR d.notes LIKE ?)'; $args[] = '%' . $filters['q'] . '%'; $args[] = '%' . $filters['q'] . '%'; }
        $having = match ($filters['status'] ?? '') { 'open' => 'HAVING remaining > 0', 'settled' => 'HAVING remaining = 0', 'overdue' => 'HAVING remaining > 0 AND due_date < ?', default => '' };
        if (($filters['status'] ?? '') === 'overdue') $args[] = date('Y-m-d');
        return DB::fetchAll('SELECT d.*, p.name AS person_name, p.phone, b.name AS business_name, c.name AS category_name, COALESCE(s.paid,0) paid, d.amount-COALESCE(s.paid,0) remaining FROM debts d JOIN people p ON p.id=d.person_id JOIN businesses b ON b.id=d.business_id JOIN categories c ON c.id=d.category_id LEFT JOIN (SELECT debt_id,SUM(amount) paid FROM transactions WHERE debt_id IS NOT NULL GROUP BY debt_id) s ON s.debt_id=d.id WHERE ' . implode(' AND ', $where) . " $having ORDER BY (remaining=0), (d.due_date IS NULL), d.due_date, d.id DESC", $args);
    }
}
