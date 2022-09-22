<?php
namespace App\CSV;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerParser
{
    const CUSTOMER_AFFILIATE_ID = 3;
    const NUM_CLIENT_GX = 4;
    const CUSTOMER_COMPANY = 6;
    const RAISONSOCIALE_2 = 6;
    const RAISONSOCIALE = 7;
    const ADDRESS_ALIAS = 7;
    const CUSTOMER_SIRET = 8;
    const ADDRESS_ADDRESS_1 = 9;
    const ADDRESS_ADDRESS_2 = 10;
    const ADDRESS_ADDRESS_3 = 11;
    const ADDRESS_POSTCODE = 12;
    const ADDRESS_CITY = 13;
    const CUSTOMER_STATUS_GX = 14;
    const CONTACT_TYPE_STATUS_GX = 14;
    const CUSTOMER_NAF = 15;
    const CUSTOMER_SEGMENTATION = 16;
    const CUSTOMER_TELEPHONE = 17;
    const CUSTOMER_EMAIL = 18;
    const CUSTOMER_SITEWEB = 19;
    const CUSTOMER_ORIGIN_GX = 20;
    const CUSTOMER_TRANCHE_EFFECTIF = 21;
    const CUSTOMER_CATEGORY_CODE_GX = 22;
    const CONTACT_GENDER = 25;
    const CONTACT_NAME = 26;
    const ADDRESS_LASTNAME = 26;
    const ADDRESS_FIRSTNAME = 27;
    const CONTACT_FIRSTNAME = 27;
    const CONTACT_QUALITE_GX = 28;
    const CONTACT_TELEPHONE = 29;
    const CONTACT_MOBILE = 30;
    const CONTACT_EMAIL = 31;
    /**
     * Read CSV file
     * @param file_path, skipHeader
     *
     * if you set skipHeader to true, it skip the header
     * @return array
     */
    public function readFile($filePath, $skipHeader = true){
        $file = fopen($filePath, 'r');
        $lines = [];
        $cnt_of_lines = 0;
        while (($line = fgets($file)) !== FALSE) {
            // Ensure the line we read is encoded properly
            $value = mb_check_encoding($line, 'UTF-8') ? $line : utf8_encode($line);
            if($cnt_of_lines == 0 && $skipHeader){
            }else{
                $lines[] = str_getcsv($value, ";");
            }
            $cnt_of_lines++;
        }
        fclose($file);
        return $lines;
    }

    /**
     * Insert Customer
     */
    public function importToDB($data){
        if($data[self::CUSTOMER_NAF] == '00000' || $data[self::CUSTOMER_NAF] == "") return;
        // $customerStatusId = $this->getCustomerStatus($data[self::CUSTOMER_STATUS_GX]) ?? 1;
        // $customerOriginId = $this->getCustomerOrigin($data[self::CUSTOMER_ORIGIN_GX]) ?? 2;
        // $customerNafId = $this->getCustomerNaf($data[self::CUSTOMER_NAF]);
        // if($customerStatusId == null){
        //     Log::build([
        //         'driver' => 'single',
        //         'path' => storage_path('logs/customer_import.log'),
        //       ])->info('Customer Status not found'.PHP_EOL.'Status: '.$data[self::CUSTOMER_STATUS_GX].PHP_EOL.'Siret: '.$data[self::CUSTOMER_SIRET].PHP_EOL);
        //       return;
        // }else if($customerOriginId == null){
        //     Log::build([
        //         'driver' => 'single',
        //         'path' => storage_path('logs/customer_import.log'),
        //       ])->info('Customer Origin not found'.PHP_EOL.'Origin: '.$data[self::CUSTOMER_ORIGIN_GX].PHP_EOL.'Siret: '.$data[self::CUSTOMER_SIRET].PHP_EOL);
        //       return;
        // }else if($customerNafId == null){
        //     Log::build([
        //         'driver' => 'single',
        //         'path' => storage_path('logs/customer_import.log'),
        //       ])->info('Customer Naf not found'.PHP_EOL.'Naf: '.$data[self::CUSTOMER_NAF].PHP_EOL.'Siret: '.$data[self::CUSTOMER_SIRET].PHP_EOL);
        //       return;
        // }else{
            $customerId = $this->insertGetCustomerId($data);
            $addressId = $this->insertGetAddressId($data, $customerId);
            if($data[self::CONTACT_FIRSTNAME] == '' && $data[self::CONTACT_NAME] == ''){
                Log::build([
                    'driver' => 'single',
                    'path' => storage_path('logs/customer_import.log'),
                ])->info('This contact has neither first name or name'.PHP_EOL.'CustomerID: '.$customerId.' Siret: '.$data[self::CUSTOMER_SIRET].PHP_EOL);
                return;
            }else{
                $this->insertGetContactId($data, $customerId, $addressId);
            }
        // }
    }

    public function insertGetCustomerId($data){
        if(DB::table('customers')->where('siret', str_replace(' ', '', $data[self::CUSTOMER_SIRET]))->count()){
            return DB::table('customers')->where('siret', str_replace(' ', '', $data[self::CUSTOMER_SIRET]))->value('id');
        }else{
            return DB::table('customers')->insertGetId([
                'affiliate_id'          => $this->getCustomerAffiliateId($data[self::CUSTOMER_AFFILIATE_ID]),
                'customer_statut_id'    => $this->getCustomerStatus($data[self::CUSTOMER_STATUS_GX]) ?? 1,
                'customer_categories_id'=> $this->getCustomerCategory($data[self::CUSTOMER_CATEGORY_CODE_GX]) ?? 15,
                'customer_origin_id'    => $this->getCustomerOrigin($data[self::CUSTOMER_ORIGIN_GX]) ?? 2,
                'naf'                   => $this->getCustomerNaf($data),
                'siret'                 => str_replace(' ', '', $data[self::CUSTOMER_SIRET]),
                'email'                 => $data[self::CUSTOMER_EMAIL],
                'telephone'             => str_replace(" ", "",$data[self::CUSTOMER_TELEPHONE]),
                'company'               => $data[self::CUSTOMER_COMPANY],
                'raisonsociale'         => $data[self::RAISONSOCIALE],
                'raisonsociale2'        => $data[self::RAISONSOCIALE_2],
                'siteweb'               => $data[self::CUSTOMER_SITEWEB],
                'active'                => 1,
                'trancheeffectif'       => $data[self::CUSTOMER_TRANCHE_EFFECTIF],
                'num_client_gx'         => $data[self::NUM_CLIENT_GX],
                'signupdate'            => now()->format('Y-m-d'),
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }
    }

    public function insertGetAddressId($data, $customerId){
        $address = str_replace(" ", "", $data[self::ADDRESS_ADDRESS_1]);
        $originalAddress = $data[self::ADDRESS_ADDRESS_1];
        if($address == ''){
            $address = str_replace(" ", "", $data[self::ADDRESS_ADDRESS_2]);
            $originalAddress = $data[self::ADDRESS_ADDRESS_2];
        }
        if($address == ''){
            $address = str_replace(" ", "", $data[self::ADDRESS_ADDRESS_3]);
            $originalAddress = $data[self::ADDRESS_ADDRESS_3];
        }
        if($address == ''){
            Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/customer_import.log'),
            ])->info('This address has no info'.PHP_EOL.'CustomerID: '.$customerId.' Siret: '.$data[self::CUSTOMER_SIRET].PHP_EOL);
            return 0;
        }
        $addressId = DB::table('addresses')
                        ->where('customer_id', $customerId)
                        ->where('address1', $originalAddress)
                        ->where('postcode', $data[self::ADDRESS_POSTCODE])->value('id');
        if($addressId){
            return $addressId;
        }else{
            return DB::table('addresses')->insertGetId([
                'address_type_id'   => 3,
                'country_id'        => 1,
                'state_id'          => 0,
                'customer_id'       => $customerId,
                'alias'             => $data[self::ADDRESS_ALIAS],
                'postcode'          => $data[self::ADDRESS_POSTCODE],
                'city'              => $data[self::ADDRESS_CITY],
                'address1'          => $originalAddress,
                'address2'          => $data[self::ADDRESS_ADDRESS_2],
                'address3'          => $data[self::ADDRESS_ADDRESS_3],
                'firstname'         => $data[self::ADDRESS_FIRSTNAME],
                'lastname'          => $data[self::ADDRESS_LASTNAME],
                'gender'            => $data[self::CONTACT_GENDER],
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    public function insertGetContactId($data, $customerId, $addressId){
        if(DB::table('contacts')->where('customer_id', $customerId)->where(function($query) use($data){
            $query->where('firstname', $data[self::CONTACT_FIRSTNAME])
                ->where('name', $data[self::CONTACT_NAME]);
        })->count() == 0){
            return DB::table('contacts')->insertGetId([
                'customer_id'       => $customerId,
                'address_id'        => $addressId,
                'contact_type_id'   => $this->getContactType($data[self::CUSTOMER_STATUS_GX]) ?? 1,
                'contact_qualite_id'=> $this->getContactQualite($data[self::CONTACT_QUALITE_GX]) ?? 6,
                'name'              => $data[self::CONTACT_NAME],
                'firstname'         => $data[self::CONTACT_FIRSTNAME],
                'email'             => $data[self::CONTACT_EMAIL],
                'gender'            => $data[self::CONTACT_GENDER],
                'mobile'            => str_replace(" ", "",$data[self::CONTACT_MOBILE]),
                'telephone'         => str_replace(" ", "",$data[self::CONTACT_TELEPHONE]),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }else{
            return 0;
        }
    }

    /**
     * Get customer affilate_id from affilates table
     */
    public function getCustomerAffiliateId($customerAffilateCodeGx){
        return DB::table('affiliates')->where('codegx', $customerAffilateCodeGx)->value('id');
    }
    /**
     * Get customer status from customer_status_gx
     */
    public function getCustomerStatus($customerStatusGx){
        return DB::table('customer_statut')->where('status_gx', $customerStatusGx)->value('id');
    }

    /**
     * Get customer origin from customer_origin_gx
     */
    public function getCustomerOrigin($customerOriginGx){
        return DB::table('customer_origins')->where('origin_gx', $customerOriginGx)->value('id');
    }
    /**
     * Get customer naf
     */
    public function getCustomerNaf($data){
        $naf = DB::table('customer_naf')->where('code', $data[self::CUSTOMER_NAF])->value('id');
        if($naf){
            return $data[self::CUSTOMER_NAF];
        }else{
            DB::table('customer_naf')->insertGetId([
                'selection' => $data[self::CUSTOMER_SEGMENTATION],
                'code'      => $data[self::CUSTOMER_NAF],
                'name'      => 'DOIT ETRE MISE A JOUR',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            return $data[self::CUSTOMER_NAF];
        }
    }

    /**
     * Get customer category from customer_category_gx
     */
    public function getCustomerCategory($customerCategoryGx){
        return DB::table('customer_categories')->where('categorie_code_gx', $customerCategoryGx)->value('id');
    }

    /**
     * Get contact qualite id from contact_qualite_gx
     */
    public function getContactQualite($contactQualiteGx){
        return DB::table('contact_qualite')->where('contact_qualite_gx', $contactQualiteGx)->value('id');
    }
    /**
     * Get customer status from customer_status_gx
     */
    public function getContactType($customerStatusGx){
        return DB::table('contact_type')->where('status_gx', $customerStatusGx)->value('id');
    }
}