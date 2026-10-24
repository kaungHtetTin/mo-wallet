<?php
namespace App\Controllers;

use App\Services\Finance as F;
use App\Services\Money;
use App\ValidationException;
use Mini\Database as DB;

final class WalletController
{
    private function filters(): array
    {
        try { return F::filters(); }
        catch (ValidationException $e) {
            $_SESSION['errors'] = ['form' => reset($e->errors)];
            return ['from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'business_id' => '', 'book_id' => '', 'category_id' => '', 'kind' => '', 'q' => ''];
        }
    }

    public function dashboard(): void
    {
        $filters = $this->filters();
        $report = F::report($filters);
        $books = F::books();
        $debts = F::debts(['status' => 'open', 'business_id' => $filters['business_id']]);
        $receivable = $payable = 0;
        foreach ($debts as $debt) { if ($debt['direction'] === 'receivable') $receivable = Money::add($receivable, $debt['remaining']); else $payable = Money::add($payable, $debt['remaining']); }
        $recent = F::transactions($filters, 1, 8)['rows'];
        view('dashboard', array_merge(compact('filters','report','books','debts','receivable','payable','recent'), F::options(), ['balances' => $books, 'title' => 'Overview']));
    }

    public function transactions(): void
    {
        $filters = $this->filters();
        $listing = F::transactions($filters, max(1, (int) query('page','1')));
        view('transactions', array_merge(F::options(), compact('filters','listing'), ['title' => 'Transactions']));
    }

    public function transactionForm(?string $id = null): void
    {
        $row = $id ? F::find('transactions', (int) $id) : [];
        if (!empty($row['debt_id'])) redirect('debts/' . $row['debt_id'], t('Manage this payment from its balance detail.'), 'warning');
        view('transaction-form', array_merge(F::options(), compact('row'), ['title' => $id ? 'Edit transaction' : 'New transaction']));
    }

    public function transactionSave(?string $id = null): void
    {
        $this->claimRequest($id ? "transactions/$id/edit" : 'transactions/new');
        try { F::saveTransaction($_POST, $id ? (int) $id : null); redirect($this->returnPath('transactions'), t('Transaction saved.')); }
        catch (ValidationException $e) { AuthController::errors($e, $id ? "transactions/$id/edit" : 'transactions/new'); }
    }

    public function transactionDelete(string $id): void
    {
        $row = F::find('transactions', (int) $id);
        $return = $row['debt_id'] ? 'debts/' . $row['debt_id'] : $this->returnPath('transactions');
        try { F::delete('transactions', (int) $id); redirect($return, t('Transaction removed. Balances have been updated.')); }
        catch (ValidationException $e) { AuthController::errors($e, $return); }
    }

    public function manage(string $entity): void
    {
        $this->entity($entity);
        $rows = $entity === 'books' ? F::books() : DB::fetchAll("SELECT * FROM `$entity` ORDER BY name, id");
        if ($entity === 'people') {
            $summary = [];
            foreach (F::debts() as $debt) {
                $summary[$debt['person_id']][$debt['direction']] = Money::add($summary[$debt['person_id']][$debt['direction']] ?? 0, $debt['remaining']);
            }
        }
        view('manage', ['title' => ucfirst($entity), 'entity' => $entity, 'rows' => $rows, 'summary' => $summary ?? []]);
    }

    public function entityForm(string $entity, ?string $id = null): void
    {
        $this->entity($entity);
        $row = $id ? F::find($entity, (int) $id) : [];
        view('entity-form', ['title' => $id ? 'Edit record' : 'New record', 'entity' => $entity, 'row' => $row]);
    }

    public function entitySave(string $entity, ?string $id = null): void
    {
        $this->entity($entity);
        $this->claimRequest("manage/$entity/" . ($id ? "$id/edit" : 'new'));
        try { F::saveEntity($entity, $_POST, $id ? (int) $id : null); redirect('manage/' . $entity, t('Record saved.')); }
        catch (ValidationException $e) { AuthController::errors($e, "manage/$entity/" . ($id ? "$id/edit" : 'new')); }
        catch (\PDOException $e) { if ($e->getCode() !== '23000') throw $e; AuthController::errors(new ValidationException(['name' => 'This name is already in use.']), "manage/$entity/" . ($id ? "$id/edit" : 'new')); }
    }

    public function entityDelete(string $entity, string $id): void
    {
        $this->entity($entity);
        try { F::delete($entity, (int) $id); redirect('manage/' . $entity, t('Record removed.')); }
        catch (ValidationException $e) { AuthController::errors($e, 'manage/' . $entity); }
    }

    private function entity(string $entity): void
    {
        if (!in_array($entity, F::ENTITIES, true)) { http_response_code(404); view('errors/404'); exit; }
    }

    public function debts(): void
    {
        $filters = ['business_id' => query('business_id'), 'person_id' => query('person_id'), 'direction' => query('direction'), 'status' => query('status','open'), 'q' => mb_substr(query('q'),0,100)];
        $rows = F::debts($filters);
        $totalRows = count($rows); $pages = max(1,(int) ceil($totalRows/25)); $page = max(1,min((int) query('page','1'),$pages));
        $totals = ['receivable' => 0, 'payable' => 0];
        foreach ($rows as $row) $totals[$row['direction']] = Money::add($totals[$row['direction']], $row['remaining']);
        $rows = array_slice($rows, ($page-1)*25,25);
        view('debts', array_merge(F::options(), compact('filters','rows','totals','totalRows','pages','page'), ['title' => 'Payables & receivables']));
    }

    public function debtForm(?string $id = null): void
    {
        $row = $id ? F::find('debts', (int) $id) : [];
        view('debt-form', array_merge(F::options(), compact('row'), ['title' => $id ? 'Edit balance' : 'New unpaid balance']));
    }

    public function debtSave(?string $id = null): void
    {
        $this->claimRequest($id ? "debts/$id/edit" : 'debts/new');
        try { $saved = F::saveDebt($_POST, $id ? (int) $id : null); redirect('debts/' . $saved, t('Balance saved.')); }
        catch (ValidationException $e) { AuthController::errors($e, $id ? "debts/$id/edit" : 'debts/new'); }
    }

    public function debtDetail(string $id): void
    {
        F::find('debts', (int) $id);
        $row = F::debts(['id' => $id])[0];
        $payments = DB::fetchAll('SELECT t.*,b.name book_name FROM transactions t JOIN books b ON b.id=t.book_id WHERE t.debt_id=? ORDER BY t.record_date DESC,t.id DESC', [$id]);
        view('debt-detail', array_merge(F::options(), compact('row','payments'), ['title' => 'Balance detail']));
    }

    public function settle(string $id): void
    {
        $this->claimRequest('debts/' . $id);
        try { F::settle((int) $id, $_POST); redirect('debts/' . $id, t('Payment recorded. Account book and reports are updated.')); }
        catch (ValidationException $e) { AuthController::errors($e, 'debts/' . $id); }
    }

    public function debtDelete(string $id): void
    {
        try { F::delete('debts', (int) $id); redirect('debts', t('Balance removed.')); }
        catch (ValidationException $e) { AuthController::errors($e, 'debts/' . $id); }
    }

    public function reports(): void
    {
        $filters = $this->filters();
        $report = F::report($filters);
        view('reports', array_merge(F::options(), compact('filters','report'), ['title' => 'Reports & analysis']));
    }

    public function export(): void
    {
        try { $filters = F::filters(); }
        catch (ValidationException $e) { AuthController::errors($e, 'reports'); }
        [$where,$args] = F::where($filters);
        $stmt = DB::statement('SELECT t.id,t.record_date,t.kind,t.amount,b.name business,a.name account_book,z.name destination,c.name category,p.name person,t.debt_id,t.notes FROM transactions t LEFT JOIN businesses b ON b.id=t.business_id JOIN books a ON a.id=t.book_id LEFT JOIN books z ON z.id=t.to_book_id LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN people p ON p.id=t.person_id WHERE ' . $where . ' ORDER BY t.record_date,t.id', $args);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="mowallet-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output','w'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID','Date','Type','Amount (MMK)','Business / personal','Account book','Destination book','Category','Person','Balance ID','Notes'], ',', '"', '');
        while ($row = $stmt->fetch()) {
            $safe = array_map(fn ($v) => is_string($v) && preg_match('/^[\s]*[=+\-@\t\r\n]/u', $v) ? "'" . $v : $v, array_values($row));
            fputcsv($out, $safe, ',', '"', '');
        }
        fclose($out);
    }

    public function settings(): void
    {
        view('settings', ['title' => 'Settings']);
    }

    public function password(): void
    {
        try {
            $owner = DB::fetch('SELECT * FROM owner_users WHERE id=1');
            if (!password_verify(input('current_password'),$owner['password'])) F::fail('current_password','Current password is incorrect.');
            AuthController::password(input('password'));
            if (input('password') !== input('password_confirmation')) F::fail('password_confirmation','Passwords do not match.');
            DB::statement('UPDATE owner_users SET password=?, session_version=session_version+1 WHERE id=1', [password_hash(input('password'),PASSWORD_DEFAULT)]);
            $_SESSION['session_version'] = (int) DB::fetchValue('SELECT session_version FROM owner_users WHERE id=1');
            session_regenerate_id(true);
            redirect('settings',t('Password updated.'));
        } catch (ValidationException $e) { AuthController::errors($e,'settings'); }
    }

    private function returnPath(string $path): string
    {
        $return = input('return');
        return preg_match('#^' . preg_quote($path,'#') . '(?:\?[^\r\n]*)?$#D', $return) ? $return : $path;
    }

    private function claimRequest(string $return): void
    {
        $token = input('_request');
        if (!$token || !isset($_SESSION['mutations'][$token])) redirect($return,t('This form has already been processed or expired. Please try again.'),'warning');
        unset($_SESSION['mutations'][$token]);
    }
}
