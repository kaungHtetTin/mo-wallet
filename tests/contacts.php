<?php
/** Included by the isolated suite after owner setup. */
use App\Services\ContactImport as Import;
use Mini\Database as DB;

$importTransactions=(int)DB::fetchValue('SELECT COUNT(*) FROM transactions');
$importDebts=(int)DB::fetchValue('SELECT COUNT(*) FROM debts');

$vcf="BEGIN:VCARD\r\nVERSION:3.0\r\nFN:အောင်အောင်\r\nTEL;TYPE=CELL:09 123 456 789\r\nTEL;TYPE=HOME:01 123456\r\nEMAIL:ignored@example.test\r\nNOTE:Private original note must not be imported\r\nEND:VCARD\r\nBEGIN:VCARD\r\nVERSION:4.0\r\nN:Smith;Jane;;;\r\nTEL;VALUE=uri:tel:+1-202-555-0101\r\nEND:VCARD\r\n";
$parsed=Import::vcard($vcf);
check(count($parsed['rows'])===2 && $parsed['rows'][0]['name']==='အောင်အောင်','vCard preserves Myanmar names');
check($parsed['rows'][0]['phone']==='09 123 456 789' && str_contains($parsed['rows'][0]['notes'],'01 123456'),'Additional phone numbers retained');
check(!str_contains(json_encode($parsed),'Private original note') && !str_contains(json_encode($parsed),'ignored@example.test'),'Only name and phone data parsed');
check($parsed['rows'][1]['name']==='Jane Smith' && $parsed['rows'][1]['phone']==='+1-202-555-0101','Structured names and vCard4 tel URI');
$folded=Import::vcard("BEGIN:VCARD\nVERSION:3.0\nFN:A very long\n  name\nTEL:09123\nEND:VCARD\n");
check($folded['rows'][0]['name']==='A very long name','Folded name lines unfolded');
$qp=Import::vcard("BEGIN:VCARD\r\nVERSION:2.1\r\nFN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:Jane=20=\r\nSmith\r\nEND:VCARD\r\n");
check($qp['rows'][0]['name']==='Jane Smith','Quoted printable soft breaks decoded');
$encoded=Import::vcard("BEGIN:VCARD\nFN;ENCODING=b:".base64_encode('မြမြ')."\nEND:VCARD\n");
check($encoded['rows'][0]['name']==='မြမြ','Base64 encoded names decoded');
$utf16=Import::vcard("\xFF\xFE".mb_convert_encoding($vcf,'UTF-16LE','UTF-8'));
check($utf16['rows'][0]['name']==='အောင်အောင်','UTF16 vCard export decoded');
$latin=Import::vcard("BEGIN:VCARD\nFN;CHARSET=ISO-8859-1:Ren\xE9\nEND:VCARD\n");
check($latin['rows'][0]['name']==='René','Legacy charset names decoded');
$escaped=Import::vcard("BEGIN:VCARD\nFN:Smith\\, Jane\\; Jr.\nEND:VCARD\n");
check($escaped['rows'][0]['name']==='Smith, Jane; Jr.','Escaped vCard delimiters');
$picker=Import::picker(json_encode([['name'=>['Phone choice'],'tel'=>['09999999999','0112345']]]));
check($picker['rows'][0]['name']==='Phone choice','Phone picker payload parsed');
$numberOnly=Import::picker(json_encode([['name'=>[],'tel'=>['09123456']]]));
check($numberOnly['rows'][0]['name']==='09123456','Unnamed contacts use phone as name');
$invalidRows=Import::picker(json_encode([['name'=>['Valid'],'tel'=>[]],['name'=>[str_repeat('x',101)],'tel'=>[]],['name'=>['Bad phone'],'tel'=>[str_repeat('9',51)]]]));
check(count($invalidRows['rows'])===1 && $invalidRows['skipped']===2,'Invalid field lengths reported and skipped');
invalid(fn()=>Import::vcard('This is not a contact file.'),'Reject non-vCard content');
invalid(fn()=>Import::vcard("BEGIN:VCARD\nFN:Missing end"),'Reject incomplete vCard');
invalid(fn()=>Import::vcard("BEGIN:VCARD\nBEGIN:VCARD\nEND:VCARD"),'Reject nested malformed cards');
invalid(fn()=>Import::vcard(str_repeat('x',Import::MAX_BYTES+1)),'Reject oversized contact file');
invalid(fn()=>Import::picker(json_encode(array_fill(0,501,['name'=>['Too many'],'tel'=>[]]))),'Limit contacts per batch');
invalid(fn()=>Import::picker('{broken'),'Reject invalid picker JSON');
invalid(fn()=>Import::picker('{"name":"wrong shape"}'),'Reject non-list picker data');
invalid(fn()=>Import::picker('[["bad shape"]]'),'Reject contacts with no usable data');
$saved=Import::save($parsed['rows'],['0']);
check($saved['imported']===1 && $saved['duplicates']===0,'Import only selected contact');
check(!DB::fetchValue("SELECT COUNT(*) FROM people WHERE name='Jane Smith'"),'Unselected contact not saved');
$same=Import::picker(json_encode([['name'=>['အောင်အောင်'],'tel'=>['+95 9 123456789']]]));
check(Import::annotate($same['rows'])[0]['duplicate'],'Detect Myanmar local and international phone duplicate');
$repeat=Import::save($same['rows'],['0']);
check($repeat['imported']===0 && $repeat['duplicates']===1,'Repeated contact import skips duplicates');
$burmeseDigits=Import::picker(json_encode([['name'=>['အောင်အောင်'],'tel'=>['၀၉၁၂၃၄၅၆၇၈၉']]]));
check(Import::annotate($burmeseDigits['rows'])[0]['duplicate'],'Myanmar phone digits normalized for matching');
invalid(fn()=>Import::save($parsed['rows'],[]),'Reject empty contact selection');
invalid(fn()=>Import::save($parsed['rows'],['999']),'Reject out of range selection');
$duplicateBatch=Import::picker(json_encode([['name'=>['Repeated'],'tel'=>['099']],['name'=>['Repeated'],'tel'=>['099']]]));
check(Import::annotate($duplicateBatch['rows'])[1]['duplicate'],'Duplicates within file identified');
check(Import::save($duplicateBatch['rows'],['0','1','0'])['imported']===1,'Duplicate selections never create extra people');

$before=(int)DB::fetchValue('SELECT COUNT(*) FROM people');
$html=request('people/import');
check($html['status']===200 && str_contains($html['body'],'Contact file (.vcf)'),'Import chooser renders');
check(request('people/import/preview',[])['status']===419,'Import preview enforces CSRF');
$payload=json_encode([['name'=>['Preview only'],'tel'=>['09111']],['name'=>['Second preview'],'tel'=>['09222']]]);
$selection=array_merge(tokens($html['body']),['source'=>'phone','contacts'=>$payload]);
$preview=request('people/import/preview',$selection);
check($preview['status']===302,'Contact picker preview accepted');
check((int)DB::fetchValue('SELECT COUNT(*) FROM people')===$before,'Preview never writes people');
preg_match('~Location: /people/import\?draft=([a-f0-9]+)~',$preview['headers'],$match);
$draft=$match[1]??'';
$previewHtml=request('people/import?draft='.$draft);
check(str_contains($previewHtml['body'],'Preview only') && str_contains($previewHtml['body'],'Second preview'),'Preview shows contact choices');
$commit=array_merge(tokens($previewHtml['body']),['draft'=>$draft,'selected'=>['1']]);
check(request('people/import/save',$commit)['status']===302,'Selected contacts saved by authenticated POST');
check((int)DB::fetchValue('SELECT COUNT(*) FROM people')===$before+1 && !DB::fetchValue("SELECT id FROM people WHERE name='Preview only'"),'HTTP import saves only selected contact');
request('people/import/save',$commit);
check((int)DB::fetchValue('SELECT COUNT(*) FROM people')===$before+1,'Repeated import POST cannot duplicate contacts');
check(str_contains(request('people/import?draft='.$draft)['body'],'This preview has expired.'),'Consumed draft cannot be reused');
$expired=request('people/import/save',array_merge(tokens(request('people/import')['body']),['draft'=>str_repeat('a',32),'selected'=>['0']]));
check($expired['status']===302 && (int)DB::fetchValue('SELECT COUNT(*) FROM people')===$before+1,'Unknown or expired draft cannot write');

// Exercise PHP multipart uploads rather than only the contact parser.
$upload=tempnam(sys_get_temp_dir(),'mowallet-vcf-');
try {
    file_put_contents($upload,"BEGIN:VCARD\nVERSION:3.0\nFN:Uploaded contact\nTEL:09333\nEND:VCARD\n");
    $curl=curl_init("http://127.0.0.1:$port/people/import/preview");
    $fields=array_merge(tokens(request('people/import')['body']),['source'=>'file','contacts_file'=>new CURLFile($upload,'text/vcard','contacts.vcf')]);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields]);
    $uploaded=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
    check($status===302 && preg_match('~Location: /people/import\?draft=([a-f0-9]+)~',$uploaded,$uploadMatch)===1,'Real vCard multipart upload creates preview');
    $uploadDraft=$uploadMatch[1];
    check(str_contains(request('people/import?draft='.$uploadDraft)['body'],'Uploaded contact'),'Uploaded contact appears in preview');
    request('people/import/discard',array_merge(tokens(request('people/import?draft='.$uploadDraft)['body']),['draft'=>$uploadDraft]));
    check(!DB::fetchValue("SELECT id FROM people WHERE name='Uploaded contact'"),'Discarding preview imports nothing');
} finally { @unlink($upload); }

$html=request('people/import');
$malicious=request('people/import/preview',array_merge(tokens($html['body']),['source'=>'phone','contacts'=>json_encode([['name'=>['<script>alert(1)</script>'],'tel'=>[]]])]));
preg_match('~Location: /people/import\?draft=([a-f0-9]+)~',$malicious['headers'],$xssMatch);
$escapedHtml=request('people/import?draft='.$xssMatch[1]);
check(str_contains($escapedHtml['body'],'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($escapedHtml['body'],'<script>alert(1)</script>'),'Contact preview escapes untrusted names');
$csrf=tokens($escapedHtml['body'])['_token'];
request('locale',['_token'=>$csrf,'locale'=>'my','return'=>'people/import?draft='.$xssMatch[1]]);
check(str_contains(request('people/import?draft='.$xssMatch[1])['body'],'အဆက်အသွယ်များ ကြိုကြည့်ရန်'),'Import preview translated into Myanmar');
request('locale',['_token'=>$csrf,'locale'=>'en','return'=>'people/import']);
check((int)DB::fetchValue('SELECT COUNT(*) FROM transactions')===$importTransactions && (int)DB::fetchValue('SELECT COUNT(*) FROM debts')===$importDebts,'Contact import never changes financial records');
