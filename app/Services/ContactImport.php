<?php
declare(strict_types=1);
namespace App\Services;

use Mini\Database as DB;

/** Parses only names and phone numbers; uploaded files are never retained. */
final class ContactImport
{
    public const MAX_BYTES = 2097152;
    public const MAX_CONTACTS = 500;

    public static function vcard(string $contents): array
    {
        if (strlen($contents) > self::MAX_BYTES) Finance::fail('form','Choose a contact file smaller than 2 MB.');
        if (str_starts_with($contents,"\xFF\xFE") || str_starts_with($contents,"\xFE\xFF")) {
            $contents=mb_convert_encoding($contents,'UTF-8',str_starts_with($contents,"\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
        }
        $contents=ltrim($contents,"\xEF\xBB\xBF");
        $raw=preg_split('/\r\n|\n|\r/',$contents);
        $lines=[];
        for ($i=0,$count=count($raw);$i<$count;$i++) {
            $line=$raw[$i];
            if (stripos($line,'ENCODING=QUOTED-PRINTABLE')!==false) {
                while (str_ends_with($line,'=') && $i+1<$count) $line=substr($line,0,-1).ltrim($raw[++$i]," \t");
            }
            if ($line!=='' && ($line[0]===' ' || $line[0]==="\t") && $lines) $lines[array_key_last($lines)].=substr($line,1);
            else $lines[]=$line;
        }
        $contacts=[]; $card=null;
        foreach ($lines as $line) {
            if (strcasecmp(trim($line),'BEGIN:VCARD')===0) {
                if ($card!==null) Finance::fail('form','This contact file is incomplete or invalid. Export it again as vCard (.vcf).');
                $card=['name'=>'','structured'=>'','tel'=>[]]; continue;
            }
            if (strcasecmp(trim($line),'END:VCARD')===0) {
                if ($card===null) Finance::fail('form','This contact file is incomplete or invalid. Export it again as vCard (.vcf).');
                if (!$card['name'] && $card['structured']) {
                    $parts=self::nameParts($card['structured']);
                    $card['name']=implode(' ',array_filter([$parts[3]??'', $parts[1]??'', $parts[2]??'', $parts[0]??'', $parts[4]??''],fn($part)=>$part!==''));
                }
                $contacts[]=['name'=>[$card['name']],'tel'=>$card['tel']]; $card=null;
                if (count($contacts)>self::MAX_CONTACTS) Finance::fail('form','Import up to 500 contacts at a time. Export a smaller selection.');
                continue;
            }
            if ($card===null || !str_contains($line,':')) continue;
            [$header,$value]=explode(':',$line,2);
            $property=strtoupper(explode(';',$header,2)[0]);
            if (str_contains($property,'.')) $property=substr($property,strrpos($property,'.')+1);
            if (!in_array($property,['FN','N','TEL'],true)) continue;
            if (preg_match('/;ENCODING=QUOTED-PRINTABLE(?:;|$)/i',$header)) $value=quoted_printable_decode($value);
            elseif (preg_match('/;ENCODING=(?:B|BASE64)(?:;|$)/i',$header)) {
                $decoded=base64_decode($value,true); $value=$decoded===false ? '' : $decoded;
            }
            if (preg_match('/;CHARSET="?([^;"\s]+)"?/i',$header,$charset) && strcasecmp($charset[1],'UTF-8')!==0) {
                try { $value=mb_convert_encoding($value,'UTF-8',$charset[1]); }
                catch (\ValueError) { Finance::fail('form','This contact file uses an unsupported text encoding. Export it as UTF-8 vCard.'); }
            }
            if ($property==='FN') $card['name']=self::unescape($value);
            elseif ($property==='N') $card['structured']=$value;
            else $card['tel'][]=self::unescape(preg_replace('/^tel:/i','',rawurldecode($value)));
        }
        if ($card!==null) Finance::fail('form','This contact file is incomplete or invalid. Export it again as vCard (.vcf).');
        return self::normalize($contacts);
    }

    private static function unescape(string $text): string
    {
        return strtr($text,['\\n'=>' ','\\N'=>' ','\\,'=>',','\\;'=>';','\\\\'=>'\\']);
    }

    private static function nameParts(string $text): array
    {
        $parts=['']; $index=0; $escaped=false;
        foreach (str_split($text) as $char) {
            if ($char===';' && !$escaped) { $parts[++$index]=''; continue; }
            $parts[$index].=$char;
            $escaped=$char==='\\' && !$escaped;
        }
        return array_map(fn($part)=>self::unescape($part),$parts);
    }

    public static function picker(string $json): array
    {
        if (strlen($json)>self::MAX_BYTES) Finance::fail('form','Choose fewer contacts and try again.');
        try { $contacts=json_decode($json,true,16,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { Finance::fail('form','The selected contacts could not be read. Please try again.'); }
        if (!is_array($contacts) || !array_is_list($contacts)) Finance::fail('form','The selected contacts could not be read. Please try again.');
        return self::normalize($contacts);
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]/',' ',$text));
    }

    private static function normalize(array $contacts): array
    {
        if (count($contacts)>self::MAX_CONTACTS) Finance::fail('form','Import up to 500 contacts at a time. Export a smaller selection.');
        $rows=[]; $skipped=0;
        foreach ($contacts as $contact) {
            if (!is_array($contact) || !is_array($contact['name']??[]) || !is_array($contact['tel']??[])) { $skipped++; continue; }
            $name=$contact['name'][0]??'';
            if (!is_string($name)) { $skipped++; continue; }
            $name=self::clean($name); $phones=[]; $valid=mb_check_encoding($name,'UTF-8');
            foreach ($contact['tel']??[] as $phone) {
                if (!is_string($phone) || !mb_check_encoding($phone,'UTF-8')) { $valid=false; break; }
                $phone=self::clean($phone);
                if (mb_strlen($phone)>50) { $valid=false; break; }
                if ($phone!=='') $phones[]=$phone;
            }
            $phones=array_values(array_unique($phones));
            if ($name==='') $name=$phones[0]??'';
            $notes=count($phones)>1 ? 'Other phone numbers: ' . implode('; ',array_slice($phones,1)) : null;
            if (!$valid || $name==='' || mb_strlen($name)>100 || ($notes!==null && mb_strlen($notes)>1000)) { $skipped++; continue; }
            $rows[]=['name'=>$name,'phone'=>$phones[0]??null,'notes'=>$notes];
        }
        if (!$rows) Finance::fail('form','No usable contacts were found. Choose a vCard (.vcf) export with names or phone numbers.');
        return ['rows'=>$rows,'skipped'=>$skipped];
    }

    private static function key(array $row): string
    {
        $name=mb_strtolower(preg_replace('/\s+/u',' ',trim($row['name'])),'UTF-8');
        $phone=strtr($row['phone']??'',array_combine(preg_split('//u','၀၁၂၃၄၅၆၇၈၉',-1,PREG_SPLIT_NO_EMPTY),range(0,9)));
        $phone=preg_replace('/[^0-9a-z]/i','',mb_strtolower($phone));
        if (preg_match('/^(?:95|0095)0?(9\d{5,})$/D',$phone,$match)) $phone='0'.$match[1];
        return $name . "\0" . $phone;
    }

    public static function annotate(array $rows): array
    {
        $seen=[];
        foreach (DB::fetchAll('SELECT name,phone FROM people') as $person) $seen[self::key($person)]=true;
        foreach ($rows as &$row) {
            $key=self::key($row); $row['duplicate']=isset($seen[$key]); $seen[$key]=true;
        }
        unset($row); return $rows;
    }

    public static function save(array $rows, array $selected): array
    {
        if (!$selected || count($selected)>self::MAX_CONTACTS) Finance::fail('form','Select at least one contact to import.');
        $indices=[];
        foreach ($selected as $index) {
            if (!is_scalar($index) || !ctype_digit((string)$index) || !isset($rows[(int)$index])) Finance::fail('form','Please select valid contacts from the preview.');
            $indices[(int)$index]=true;
        }
        return DB::transaction(function () use ($rows,$indices) {
            // This single-owner row serializes parallel imports from different sessions.
            DB::statement('SELECT id FROM owner_users WHERE id=1 FOR UPDATE');
            $seen=[]; $imported=0; $duplicates=0;
            foreach (DB::fetchAll('SELECT name,phone FROM people') as $person) $seen[self::key($person)]=true;
            foreach (array_keys($indices) as $index) {
                $row=$rows[$index]; $key=self::key($row);
                if (isset($seen[$key])) { $duplicates++; continue; }
                DB::insert('people',array_intersect_key($row,array_flip(['name','phone','notes'])));
                $seen[$key]=true; $imported++;
            }
            return compact('imported','duplicates');
        });
    }
}
