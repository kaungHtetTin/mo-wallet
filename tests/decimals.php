<?php
/** Decimal regression coverage within the isolated service/HTTP suite. */
(function (): void {
    $money = \App\Services\Money::class;
    $finance = \App\Services\Finance::class;
    $db = \Mini\Database::class;
    foreach (['1' => '1.00', '1.5' => '1.50', '0.01' => '0.01', '999999999999.99' => '999999999999.99'] as $input => $expected) {
        check($finance::amount(['amount' => (string) $input]) === $expected, 'Normalize decimal amount: ' . $input);
    }
    check($finance::amount(['opening_balance' => '0.00'], 'opening_balance', true) === '0.00', 'Zero opening balance allowed');
    foreach (['0.00', '0.001', '1.234', '999999999999.999', '1000000000000.00', '-0.01', '1e2', 'NaN', 'INF', '1,000.50', true, 0.1, []] as $input) {
        invalid(fn () => $finance::amount(['amount' => $input]), 'Reject invalid or inexact decimal input');
    }
    check($money::add('0.10', '0.20') === '0.30', 'Exact decimal addition');
    check($money::subtract('0.30', '0.20') === '0.10', 'Exact decimal subtraction');
    check($money::sum(['9999999999999999999.99', '0.01']) === '10000000000000000000.00', 'Aggregate arithmetic exceeds integer range without losing precision');
    check($money::format('9999999999999999999.99') === '9,999,999,999,999,999,999.99', 'Large displays preserve every digit');
    check(money('-0.01') === '-0.01 MMK', 'Negative fractional balance displays correctly');

    $date = date('Y-m-d');
    $business = $finance::saveEntity('businesses', ['name' => 'Decimal checks', 'kind' => 'business']);
    $book = $finance::saveEntity('books', ['name' => 'Decimal source', 'opening_balance' => '10.25']);
    $destination = $finance::saveEntity('books', ['name' => 'Decimal destination', 'opening_balance' => '0']);
    $person = $finance::saveEntity('people', ['name' => 'Decimal payment person']);
    $sales = $db::fetchValue("SELECT id FROM categories WHERE name = 'Sales'");
    $rent = $db::fetchValue("SELECT id FROM categories WHERE name = 'Rent'");
    $income = ['kind' => 'income', 'amount' => '0.10', 'book_id' => $book, 'business_id' => $business, 'category_id' => $sales, 'record_date' => $date];
    $transaction = $finance::saveTransaction($income);
    $finance::saveTransaction(array_merge($income, ['amount' => '0.20']));
    $finance::saveTransaction(array_merge($income, ['kind' => 'expense', 'amount' => '0.07', 'category_id' => $rent]));
    $finance::saveTransaction(['kind' => 'transfer', 'amount' => '0.05', 'book_id' => $book, 'to_book_id' => $destination, 'record_date' => $date]);
    $filters = ['from' => $date, 'to' => $date, 'business_id' => (string) $business];
    $report = $finance::report($filters);
    check($report['totals']['income'] === '0.30' && $report['totals']['expense'] === '0.07' && $report['totals']['net'] === '0.23', 'Reports keep fractional income, expenses and net');
    $balances = array_column($finance::books(), 'balance', 'id');
    check($balances[$book] === '10.43' && $balances[$destination] === '0.05', 'Book balances and transfers keep fractions');
    $finance::saveTransaction(array_merge($income, ['amount' => '0.11']), $transaction);
    check($finance::find('transactions', $transaction)['amount'] === '0.11', 'Transaction edits retain decimals');
    $finance::saveEntity('books', ['name' => 'Decimal source', 'opening_balance' => '10.26'], $book);
    check($finance::find('books', $book)['opening_balance'] === '10.26', 'Opening balance edits retain decimals');

    $debt = ['direction' => 'receivable', 'amount' => '0.30', 'person_id' => $person, 'business_id' => $business, 'category_id' => $sales, 'record_date' => $date];
    $debtId = $finance::saveDebt($debt);
    $payment = $finance::settle($debtId, ['amount' => '0.10', 'book_id' => $book, 'record_date' => $date]);
    $row = $finance::debts(['id' => $debtId])[0];
    check($row['remaining'] === '0.20' && debt_status($row) === 'Partial', 'Fractional unpaid balance remains partial');
    invalid(fn () => $finance::settle($debtId, ['amount' => '0.21', 'book_id' => $book, 'record_date' => $date]), 'Reject overpayment by one hundredth');
    invalid(fn () => $finance::saveDebt(array_merge($debt, ['amount' => '0.09']), $debtId), 'Reject decimal debt below paid amount');
    invalid(fn () => $finance::saveDebt(array_merge($debt, ['person_id' => 1]), $debtId), 'Fractional payments prevent ownership changes');
    $finance::settle($debtId, ['amount' => '0.20', 'book_id' => $book, 'record_date' => $date]);
    $row = $finance::debts(['id' => $debtId])[0];
    check($row['remaining'] === '0.00' && debt_status($row) === 'Settled', 'Exact fractional payments fully settle debt');
    $finance::delete('transactions', $payment);
    check($finance::debts(['id' => $debtId])[0]['remaining'] === '0.10', 'Undo restores exact fractional balance');
    $finance::saveDebt(array_merge($debt, ['amount' => '0.31']), $debtId);
    check($finance::debts(['id' => $debtId])[0]['remaining'] === '0.11', 'Debt edits retain decimals');
    $payable = $finance::saveDebt(array_merge($debt, ['direction' => 'payable', 'amount' => '0.03', 'category_id' => $rent]));
    $finance::settle($payable, ['amount' => '0.01', 'book_id' => $book, 'record_date' => $date]);
    check($finance::debts(['id' => $payable])[0]['remaining'] === '0.02', 'Payable settlement retains decimals');

    $form = request('transactions/new');
    check(str_contains($form['body'], 'step="0.01"') && str_contains($form['body'], 'inputmode="decimal"'), 'Transaction form accepts decimal input');
    $response = request('transactions/new', array_merge(tokens($form['body']), $income, ['amount' => '12.34', 'notes' => 'Decimal HTTP check']));
    check($response['status'] === 302 && $db::fetchValue("SELECT amount FROM transactions WHERE notes = 'Decimal HTTP check'") === '12.34', 'HTTP save stores exact decimals');
    check(str_contains(request('transactions?business_id=' . $business)['body'], '12.34'), 'Ledger displays decimal amounts');
    $detail = request('debts/' . $debtId);
    check(str_contains($detail['body'], 'max="0.11"') && str_contains($detail['body'], 'value="0.11"'), 'Settlement form preserves fractional remaining limit');
    check(str_contains(request('reports?business_id=' . $business)['body'], '12.77 MMK'), 'Report renders decimal net income');
    $csv = request('export?from=' . $date . '&to=' . $date . '&business_id=' . $business);
    check(str_contains($csv['body'], ',12.34,') && str_contains($csv['body'], ',0.20,'), 'CSV exports exact decimal amounts');
    $response = request('debts/' . $debtId . '/settle', array_merge(tokens($detail['body']), ['amount' => '0.11', 'book_id' => $book, 'record_date' => $date]));
    check($response['status'] === 302 && $finance::debts(['id' => $debtId])[0]['remaining'] === '0.00', 'HTTP settlement accepts fractional full payment');
})();

