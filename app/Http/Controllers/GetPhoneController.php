<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Phones;
use GuzzleHttp;
use Illuminate\Support\Facades\Http;

class GetPhoneController extends Controller
{
    public function __construct()
    {
        ini_set('max_execution_time', 180);
    }

    public function index()
    {
        $domainList = [
            "https://www.example.com/",
            "https://www.example.com/",
            "https://www.example.com/",
            "https://www.example.com/",
            "https://www.example.com/",
            "https://www.example.com/"
        ];

        foreach ($domainList as $domain) {
            $allData = $this->getAllDatas($domain);
            $phoneNumber = $this->getPhone($allData);
            $phoneNumber = $this->cleanMatris($phoneNumber);
            $phoneNumber = $this->readPhone($phoneNumber, $domain);
            $status = $this->insertPhone($phoneNumber, $domain);
            if ($status) {
                echo "<b>" . $domain . "</b> adresinin telefon numaraları alındı.<br><br>";
            }
        }
    }


    public function getPhone($allData)
    {

        /*
        Telefon yazım tipine göre değer boş gelirse
        Farklı yazım tipinde dene
        Örnek 1 : 055* *** ** **
        Örnek 2 : +90 (****) *** ** **
        Örnek 3 : 055* *** ****
        Örnek 4 : (0553) *** ****
        Örnek 5 : (553) *** ****
        Örnek 6 : 0553*******
        Örnek 7 : +90 5** *** ** **
        ...
        ...
        ...
        Regex kodları telefon numaralarının patternlerine göre artabilir.
        */

        $pattern = [
            '@[0-9]{4}\s[0-9]{3}\s[0-9]{2}\s[0-9]{2}@', // Örnek 1
            '@\+[0-9]{2}\s\([0-9]{3}\)\s[0-9]{3}\s[0-9]{2}\s[0-9]{2}@', // Örnek 2
            '@[0-9]{4}\s[0-9]{3}\s[0-9]{4}@', // Örnek 3
            '@([0-9]{4})\s[0-9]{3}\s[0-9]{4}@', // Örnek 4
            '@([0-9]{3})\s[0-9]{3}\s[0-9]{4}@', // Örnek 5
            '@05[0-9]{9}@', // Örnek 6
            '@\+[0-9]{2}\s[0-9]{3}\s[0-9]{3}\s[0-9]{2}\s[0-9]{2}@', // Örnek 7
        ];


        $counter = 0;
        foreach ($pattern as $value) {
            preg_match_all($value, $allData, $phones[$counter]);
//            echo "Pregmatch altı : ";
//            print_r($phones[$counter]);
//            echo "<br>";
            if (!empty($phones[$counter][0])) {
                if (strlen($phones[$counter][0][0]) > 5) {
                    break;
                }
            }

            $counter++;
        }


//        echo " Adresinin Pregmatch altı FOR SONU: ";
//        print_r($phones);
//        echo "<br> Sayaç son -1 çıkar : ".$counter;


        return $phones;
    }

    public function getAllDatas($domain)
    {
// CURL İLE KULLANMAK İSTİYORSAN YORUM SATIRLARINI AÇIP GUZZLE KODLARINI YORUM SATIRINA AL
//        $ch = curl_init();
//
//        curl_setopt_array($ch, [
//            CURLOPT_URL => $domain,
//            CURLOPT_CUSTOMREQUEST => "GET",
//            CURLOPT_USERAGENT =>
//                "Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Safari/537.36",
//            CURLOPT_SSL_VERIFYPEER => false,
//            CURLOPT_SSL_VERIFYHOST => false,
//            CURLOPT_RETURNTRANSFER => true
//        ]);
//
//        return curl_exec($ch);

        $response = Http::get($domain);
        return $response->body();


    }

    public function cleanMatris($matris)
    {
        // Sorgulardan sonra gelen boş değerleri temizle
        for ($row = 0; $row < count($matris); $row++) {
            for ($column = 0; $column <= count($matris[$row]); $column++) {
                if (empty($matris[$row][$column])) {
                    unset($matris[$row][$column]);
                }
            }

        }

        for ($i = 0; $i <= count($matris); $i++) {
            if (empty($matris[$i])) {
                unset($matris[$i]);
            }
        }

        return $matris;

    }

    public function readPhone($array, $domain)
    {
        // Okunan telefon numarası Pattern numarasının alt değerinde olduğu için
        // Alt indislerde olan telefon numaralarını al
        $readPhone = [];
        foreach ($array as $rowKey => $rowValue) {
            foreach ($rowValue as $columnKey => $columnValue) {
                $readPhone = $columnValue;
            }
        }

        // Telefon numaralarını okuduktan sonra
        // Tekrar eden numaraları kaldır
        $readPhone = array_unique($readPhone);

        return $readPhone;
    }

    public function insertPhone($phoneNumber, $domain)
    {

        $counter = 0;
        foreach ($phoneNumber as $value) {
            $allPhonesNumberInDomain[$counter] = $this->rewritePhoneNumber($value);
            $counter++;
        }

        if (!isset($allPhonesNumberInDomain)) {
            echo "<h1>" . $domain . " domaininde telefon numarası olmayabilir eğer var ise telefon numarasına uyumlu REGEX kodunu yazınız.</h1>";
            return 0;
        }

        $allPhonesNumberInDomain = json_encode($allPhonesNumberInDomain, true);

        $status = Phones::insert([
            "domain" => $domain,
            "phone_number" => $allPhonesNumberInDomain
        ]);

//        echo "İnsertphone methodu json encoded: ";
//        print_r($allPhonesNumberInDomain);
//        echo "<br> json decoded: ";
//        print_r(json_decode($allPhonesNumberInDomain));


        if ($status) {
            return 1;
        } else {
            return 0;
        }

    }

    public function rewritePhoneNumber($phoneNumber)
    {
        // Okunan numaraları DB'e sabit yazım tipinde ekleyeceğimiz için özel karakterleri kaldır
        $phoneNumber = str_replace(' ', '', $phoneNumber);
        $phoneNumber = str_replace('(', '', $phoneNumber);
        $phoneNumber = str_replace(')', '', $phoneNumber);
        $phoneNumber = str_replace('+9', '', $phoneNumber);
        return $phoneNumber;
    }
}
