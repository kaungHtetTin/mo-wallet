<?php
/** Isolated service and HTTP regression tests. Never uses the application database. */
declare(strict_types=1);

$testDatabase = 'mowallet_test_' . bin2hex(random_bytes(5));
putenv('DB_DATABASE=' . $testDatabase);
putenv('APP_URL=');
putenv('SESSION_NAME=mowallet_test_session');
require dirname(__DIR__) . '/bootstrap/app.php';

use App\Services\Finance as F;
use App\ValidationException;
use Mini\Database as DB;

$checks = 0; $server = null; $keep = in_array('--keep', $argv, true);
$cookie = tempnam(sys_get_temp_dir(), 'mowallet-cookie-');
$log = tempnam(sys_get_temp_dir(), 'mowallet-server-');
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function invalid(callable $action, string $label): void {
    try { $action(); } catch (ValidationException $e) { check(true, $label); return; }
    throw new RuntimeException('FAIL: Expected validation rejection: ' . $label);
}
function request(string $path, ?array $data = null): array {
    global $port, $cookie;
    $curl = curl_init("http://127.0.0.1:$port/$path");
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>10]);
    if ($data !== null) curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);
    $response = curl_exec($curl);
    if ($response === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl,CURLINFO_HTTP_CODE); $size=curl_getinfo($curl,CURLINFO_HEADER_SIZE); curl_close($curl);
    return ['status'=>$status,'headers'=>substr($response,0,$size),'body'=>substr($response,$size)];
}
function tokens(string $body): array {
    preg_match('/name="_token" value="([a-f0-9]+)"/', $body, $csrf);
    preg_match('/name="_request" value="([a-f0-9]+)"/', $body, $request);
    return ['_token'=>$csrf[1]??'', '_request'=>$request[1]??''];
}
function form(string $path, array $data): array {
    $get=request($path); check($get['status']===200, 'Form renders: ' . $path);
    return request($path, array_merge(tokens($get['body']),$data));
}

try {
    $admin=DB::connection(true);
    $admin->exec("CREATE DATABASE `$testDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $migrationFiles=glob(BASE_PATH.'/migrations/*.php'); sort($migrationFiles);
    foreach ($migrationFiles as $migrationFile) {
        if (basename($migrationFile) === '2026_10_24_000003_decimal_amounts.php') {
            $legacyBook = DB::insert('books', ['name' => 'Legacy migration check', 'opening_balance' => '123456789012']);
        }
        $migration=require $migrationFile;
        foreach ($migration['up'] as $sql) DB::connection()->exec($sql);
    }
    check(DB::fetchValue('SELECT opening_balance FROM books WHERE id = ?', [$legacyBook]) === '123456789012.00', 'Decimal migration preserves existing whole amounts');
    DB::statement('DELETE FROM books WHERE id = ?', [$legacyBook]);
    $business=F::saveEntity('businesses',['name'=>'Design Studio','kind'=>'business']);
    $business2=F::saveEntity('businesses',['name'=>'Neighborhood Store','kind'=>'business']);
    $bank=F::saveEntity('books',['name'=>'Bank','opening_balance'=>'1000000']);
    $cash=(int) DB::fetchValue("SELECT id FROM books WHERE name='Cash'");
    $sales=(int) DB::fetchValue("SELECT id FROM categories WHERE name='Sales'");
    $rent=(int) DB::fetchValue("SELECT id FROM categories WHERE name='Rent'");
    $person=F::saveEntity('people',['name'=>'မောင်မောင်','phone'=>'09123456789']);
    $person2=F::saveEntity('people',['name'=>'Studio landlord']);
    $date=date('Y-m-d');
    $income=['kind'=>'income','amount'=>'300000','book_id'=>$cash,'business_id'=>$business,'category_id'=>$sales,'record_date'=>$date,'notes'=>'Website project'];
    $incomeId=F::saveTransaction($income);
    $expense=['kind'=>'expense','amount'=>'50000','book_id'=>$bank,'business_id'=>$business,'category_id'=>$rent,'record_date'=>$date,'notes'=>'Studio rent'];
    $expenseId=F::saveTransaction($expense);
    $transfer=['kind'=>'transfer','amount'=>'100000','book_id'=>$bank,'to_book_id'=>$cash,'record_date'=>$date,'notes'=>'Cash withdrawal'];
    $transferId=F::saveTransaction($transfer);
    $all=['from'=>'1900-01-01','to'=>'9999-12-31'];
    $report=F::report($all);
    check((int)$report['totals']['income']===300000 && (int)$report['totals']['expense']===50000 && $report['totals']['net']==='250000.00','Transfers and opening balances excluded from report');
    $books=array_column(F::books(),'balance','id');
    check((int)$books[$cash]===400000 && (int)$books[$bank]===850000,'Transfer updates both books');
    check(\App\Services\Money::sum($books)==='1250000.00','Transfer conserves total book balances');
    invalid(fn()=>F::saveTransaction(array_merge($transfer,['to_book_id'=>$bank])),'Reject same-book transfer');
    foreach (['0','-1','1.501','1e5','1000000000000',[]] as $value) invalid(fn()=>F::saveTransaction(array_merge($income,['amount'=>$value])),'Reject invalid MMK amount');
    invalid(fn()=>F::saveTransaction(array_merge($income,['category_id'=>$rent])),'Reject mismatched category');
    invalid(fn()=>F::saveTransaction(array_merge($income,['record_date'=>'2026-02-30'])),'Reject invalid calendar date');
    invalid(fn()=>F::saveTransaction(array_merge($income,['book_id'=>999999])),'Reject missing book');
    F::saveTransaction(array_merge($income,['amount'=>'400000']),$incomeId);
    check((int)F::report($all)['totals']['income']===400000,'Editing transaction recalculates report');
    F::delete('transactions',$transferId);
    $books=array_column(F::books(),'balance','id');
    check((int)$books[$cash]===400000 && (int)$books[$bank]===950000,'Deleting transfer reverses both sides');
    $debt=['direction'=>'receivable','person_id'=>$person,'business_id'=>$business,'category_id'=>$sales,'amount'=>'500000','record_date'=>$date,'due_date'=>$date,'notes'=>'Final project installment'];
    $debtId=F::saveDebt($debt);
    check((int)F::report($all)['totals']['income']===400000,'Unpaid balance does not count as income');
    $payment=F::settle($debtId,['amount'=>'200000','book_id'=>$bank,'record_date'=>$date]);
    $balance=F::debts(['id'=>$debtId])[0];
    check((int)$balance['remaining']===300000 && (int)$balance['paid']===200000,'Partial payment reduces outstanding amount');
    check((int)F::report($all)['totals']['income']===600000,'Payment counts once as income');
    invalid(fn()=>F::settle($debtId,['amount'=>'300001','book_id'=>$bank,'record_date'=>$date]),'Reject overpayment');
    invalid(fn()=>F::saveDebt(array_merge($debt,['amount'=>'199999']),$debtId),'Cannot reduce debt below payments');
    invalid(fn()=>F::saveDebt(array_merge($debt,['person_id'=>$person2]),$debtId),'Cannot change paid balance ownership');
    invalid(fn()=>F::saveTransaction($income,$payment),'Payment cannot be edited as normal transaction');
    invalid(fn()=>F::delete('debts',$debtId),'Cannot delete debt with payments');
    invalid(fn()=>F::delete('people',$person),'Cannot delete referenced person');
    invalid(fn()=>F::saveEntity('categories',['name'=>'Sales','kind'=>'expense'],$sales),'Cannot change used category to conflicting type');
    F::settle($debtId,['amount'=>'300000','book_id'=>$bank,'record_date'=>$date]);
    check((int)F::debts(['id'=>$debtId])[0]['remaining']===0,'Full payment settles balance');
    check(count(F::debts(['id'=>$debtId,'status'=>'settled']))===1,'Settled filter finds paid balance');
    F::delete('transactions',$payment);
    check((int)F::debts(['id'=>$debtId])[0]['remaining']===200000,'Undo payment reopens balance');
    $payable=F::saveDebt(['direction'=>'payable','person_id'=>$person2,'business_id'=>$business,'category_id'=>$rent,'amount'=>'80000','record_date'=>$date]);
    F::settle($payable,['amount'=>'20000','book_id'=>$cash,'record_date'=>$date]);
    check((int)F::report($all)['totals']['expense']===70000,'Payable payment creates expense');
    check((int)F::debts(['id'=>$payable])[0]['remaining']===60000,'Payable payment reduces amount owed');
    F::saveTransaction(['kind'=>'income','amount'=>'100000','book_id'=>$cash,'business_id'=>$business2,'category_id'=>$sales,'record_date'=>$date,'notes'=>'Shop sales']);
    check((int)F::report(array_merge($all,['business_id'=>$business2]))['totals']['income']===100000,'Business filter isolates cash flow');
    check((int)F::report(array_merge($all,['category_id'=>$rent]))['totals']['income']===0,'Category filter isolates report');
    check(F::transactions(array_merge($all,['q'=>'Shop sales']))['total']===1,'Transaction search');
    check(F::transactions($all,999)['page']===F::transactions($all,999)['pages'],'Pagination clamps out of range page');
    $port=random_int(18100,18999);
    $server=proc_open([PHP_BINARY,'-S',"127.0.0.1:$port",'-t',BASE_PATH.'/public',BASE_PATH.'/public/router.php'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,BASE_PATH);
    if (!is_resource($server)) throw new RuntimeException('Cannot launch test server');
    for($i=0;$i<50;$i++){ $socket=@fsockopen('127.0.0.1',$port); if($socket){fclose($socket);break;} usleep(100000); }
    check(request('')['status']===302,'Dashboard requires authentication');
    check(request('export')['status']===302,'CSV export requires authentication');
    check(request('settings/branding',[])['status']===302,'Branding changes require authentication');
    check(request('setup',[])['status']===419,'Setup enforces CSRF');
    $setup=form('setup',['name'=>'Test Owner','email'=>'owner@example.test','password'=>'Wallet-Test-2026!','password_confirmation'=>'Wallet-Test-2026!']);
    check($setup['status']===302,'First owner created');
    check((int)DB::fetchValue('SELECT COUNT(*) FROM owner_users')===1,'Exactly one owner');
    check(request('setup')['status']===302,'Setup closes after first owner');
    require __DIR__ . '/contacts.php';
    require __DIR__ . '/branding.php';
    require __DIR__ . '/decimals.php';
    check(request('transactions/new',[])['status']===419,'Financial writes enforce CSRF');
    foreach(['','transactions','transactions/new','debts','debts/new',"debts/$debtId",'reports','manage/books','manage/businesses','manage/categories','manage/people','settings'] as $path){
        $response=request($path); check($response['status']===200 && !preg_match('/(?:Warning:|Fatal error|Application error)/',$response['body']),'Page renders: '.$path);
    }
    $form=request('transactions/new'); $fields=array_merge(tokens($form['body']),$income,['amount'=>'12345','notes'=>'HTTP record']);
    $before=(int)DB::fetchValue('SELECT COUNT(*) FROM transactions');
    check(request('transactions/new',$fields)['status']===302,'Transaction POST works');
    request('transactions/new',$fields);
    check((int)DB::fetchValue('SELECT COUNT(*) FROM transactions')===$before+1,'Repeated submit does not duplicate transaction');
    $form=request("debts/$debtId"); $fields=array_merge(tokens($form['body']),['amount'=>'10000','book_id'=>$cash,'record_date'=>$date]);
    $paymentResponse=request("debts/$debtId/settle",$fields);
    check($paymentResponse['status']===302,'HTTP partial settlement works');
    request("debts/$debtId/settle",$fields);
    check((int)F::debts(['id'=>$debtId])[0]['remaining']===190000,'Repeated settlement is idempotent');
    $form=request('transactions/new');
    request('transactions/new',array_merge(tokens($form['body']),$income,['amount'=>'-5']));
    $errorPage=request('transactions/new');
    check(str_contains($errorPage['body'],'Enter a positive MMK amount with up to 2 decimal places'),'Validation message shown');
    check(str_contains($errorPage['body'],'value="-5"'),'Invalid input retained for correction');
    $csrf=tokens(request('settings')['body'])['_token'];
    request('locale',['_token'=>$csrf,'locale'=>'my','return'=>'reports']);
    $my=request('reports');
    check(str_contains($my['body'],'lang="my"') && str_contains($my['body'],'အစီရင်ခံစာနှင့် သုံးသပ်ချက်'),'Myanmar locale renders');
    request('locale',['_token'=>$csrf,'locale'=>'en','return'=>'reports']);
    F::saveTransaction(array_merge($income,['amount'=>'1','notes'=>'=HYPERLINK("https://example.test")']));
    $csv=request('export?from=1900-01-01&to=9999-12-31&business_id='.$business2);
    check($csv['status']===200 && str_contains($csv['headers'],'text/csv') && str_contains($csv['body'],'Shop sales') && !str_contains($csv['body'],'Website project'),'CSV respects filters');
    check(str_contains(request('export?from=1900-01-01&to=9999-12-31')['body'],"'=HYPERLINK"),'CSV neutralizes formula injection');
    check(request('manage/not-a-table')['status']===404,'Invalid management route returns 404');
    check(request('transactions/999999/edit')['status']===404,'Missing transaction returns 404');
    $badDates=request('reports?from=2026-02-30&to=2026-01-01');
    check($badDates['status']===200 && str_contains($badDates['body'],'Enter a valid date.'),'Invalid report dates handled without error');
    $password=request('settings/password',array_merge(tokens(request('settings')['body']),['current_password'=>'Wallet-Test-2026!','password'=>'Wallet-New-2026!','password_confirmation'=>'Wallet-New-2026!']));
    check($password['status']===302 && password_verify('Wallet-New-2026!',DB::fetchValue('SELECT password FROM owner_users WHERE id=1')),'Password change hashes new password');
    check(request('')['status']===200,'Current session remains valid after password change');
    request('logout',tokens(request('settings')['body']));
    check(request('')['status']===302,'Logout protects dashboard again');
    check(form('login',['email'=>'owner@example.test','password'=>'Wallet-New-2026!'])['status']===302 && request('')['status']===200,'Login succeeds with changed password');
    echo "PASS: $checks checks\n";
    if($keep) echo "TEST_DB=$testDatabase\n";
} catch(Throwable $e) {
    $keep=false;
    fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");
    $failed=true;
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    @unlink($cookie); @unlink($log);
    if(!$keep && preg_match('/^mowallet_test_[a-f0-9]{10}$/D',$testDatabase)) DB::connection(true)->exec("DROP DATABASE IF EXISTS `$testDatabase`");
}
exit(isset($failed)?1:0);
