<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\ContactImport as Import;
use App\Services\Finance;
use App\ValidationException;

final class ContactImportController
{
    private const PATH='people/import';

    public function index(): void
    {
        $this->expireDrafts();
        $id=query('draft'); $draft=$_SESSION['contact_imports'][$id]??null;
        if ($id && !$draft) $_SESSION['errors']=['form'=>'This preview has expired. Please choose your contacts again.'];
        if ($draft) $draft['rows']=Import::annotate($draft['rows']);
        view('contact-import',['title'=>'Import contacts','draft'=>$draft,'draftId'=>$id]);
    }

    public function preview(): void
    {
        $this->claimRequest();
        try {
            if (input('source')==='phone') $result=Import::picker(input('contacts'));
            else {
                $file=$_FILES['contacts_file']??[];
                if (!isset($file['error']) || !is_int($file['error'])) Finance::fail('form','Choose a vCard (.vcf) contact file.');
                if (in_array($file['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)) Finance::fail('form','Choose a contact file smaller than 2 MB.');
                if ($file['error']!==UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) Finance::fail('form','The contact file could not be uploaded. Please choose it again.');
                if (!in_array(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)),['vcf','vcard'],true)) Finance::fail('form','Choose a vCard (.vcf) contact file.');
                if ($file['size']>Import::MAX_BYTES) Finance::fail('form','Choose a contact file smaller than 2 MB.');
                $contents=file_get_contents($file['tmp_name']);
                if ($contents===false) Finance::fail('form','The contact file could not be uploaded. Please choose it again.');
                $result=Import::vcard($contents);
            }
            $this->expireDrafts(true);
            $id=bin2hex(random_bytes(16));
            $_SESSION['contact_imports'][$id]=$result+['created'=>time()];
            redirect(self::PATH.'?draft='.$id);
        } catch (ValidationException $e) {
            $_SESSION['errors']=$e->errors; redirect(self::PATH);
        }
    }

    public function commit(): void
    {
        $this->claimRequest(); $this->expireDrafts();
        $id=input('draft'); $draft=$_SESSION['contact_imports'][$id]??null;
        if (!$draft) redirect(self::PATH,t('This preview has expired. Please choose your contacts again.'),'warning');
        try {
            $selected=$_POST['selected']??[];
            if (!is_array($selected)) Finance::fail('form','Please select valid contacts from the preview.');
            $result=Import::save($draft['rows'],$selected);
            unset($_SESSION['contact_imports'][$id]);
            $message=t('Contacts imported').': '.$result['imported'].' · '.t('Duplicates skipped').': '.$result['duplicates'];
            redirect('manage/people',$message);
        } catch (ValidationException $e) {
            $_SESSION['errors']=$e->errors; redirect(self::PATH.'?draft='.$id);
        }
    }

    public function discard(): void
    {
        unset($_SESSION['contact_imports'][input('draft')]);
        redirect(self::PATH);
    }

    private function expireDrafts(bool $reserve=false): void
    {
        foreach ($_SESSION['contact_imports']??[] as $id=>$draft) if (time()-$draft['created']>1800) unset($_SESSION['contact_imports'][$id]);
        if ($reserve && count($_SESSION['contact_imports']??[])>=5) $_SESSION['contact_imports']=array_slice($_SESSION['contact_imports'],-4,null,true);
    }

    private function claimRequest(): void
    {
        $token=input('_request');
        if (!$token || !isset($_SESSION['mutations'][$token])) redirect(self::PATH,t('This form has already been processed or expired. Please try again.'),'warning');
        unset($_SESSION['mutations'][$token]);
    }
}
