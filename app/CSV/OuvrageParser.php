<?php
namespace App\CSV;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OuvrageParser
{
    const CODE = 0;
    const REFERENCE = 1;
    const CODELCDT = 1;
    const METIER = 2;
    const PRESTATION = 3;
    const TOIT = 4;
    const OUVRAGE_NAME = 5;
    const TASK_NAME = 6;
    const TASK_TEXT_CLIENT = 7;
    const TASK_TEXT_CHARGE = 9;
    const OUVRAGE_UNIT = 11;
    const PRODUCT_REFERENCE = 13;
    const PRODUCT_NAME = 14;
    const PRODUCT_UNIT = 15;
    const OUVRAGE_DETAIL_QTY = 16;
    const PRODUCT_PRICE = 17;

    public $installtionList = ['Installation et Repli'];
    public $securiteList = ['Sécurité Temporaire EPC', 'Sécurisation', 'Sécurité Temporaire EPI'];
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
                $lines[] = str_getcsv($value);
            }
            $cnt_of_lines++;
        }
        fclose($file);
        return $lines;
    }

    /**
     * Check type of ouvrage
     */
    public function getOuvrageType($ouvrageName){
        if(in_array($ouvrageName, $this->installtionList)){
            $type = 'INSTALLATION';
        }else if(in_array($ouvrageName, $this->securiteList)){
            $type = 'SECURITE';
        }else{
            $type = 'PRESTATION';
        }
        return $type;
    }

    /**
     * Get Ouvrage Metier Id
     */
    public function getMetierId($metier){
        if(DB::table('ouvrage_metier')->where('name', $metier)->count()){
            return DB::table('ouvrage_metier')->where('name', $metier)->first()->id;
        }else{
            return DB::table('ouvrage_metier')->insertGetId([
                'name'  => $metier,
                'created_at'=> Carbon::now(),
                'updated_at'=> Carbon::now()
            ]);
        }
    }

    /**
     * Get Ouvrage Prestation Id
     */
    public function getPrestationId($prestation){
        if(DB::table('ouvrage_prestation')->where('name', $prestation)->count()){
            return DB::table('ouvrage_prestation')->where('name', $prestation)->first()->id;
        }else{
            return DB::table('ouvrage_prestation')->insertGetId([
                'name'  => $prestation,
                'created_at'=> Carbon::now(),
                'updated_at'=> Carbon::now()
            ]);
        }
    }

    /**
     * Get Ouvrage Toit Id
     */
    public function getToitId($toit){
        if(DB::table('ouvrage_toit')->where('name', $toit)->count()){
            return DB::table('ouvrage_toit')->where('name', $toit)->first()->id;
        }else{
            return DB::table('ouvrage_toit')->insertGetId([
                'name'  => $toit,
                'image' => '',
                'created_at'=> Carbon::now(),
                'updated_at'=> Carbon::now()
            ]);
        }
    }
    /**
     * Get Ouvrage Unit Id
     */
    public function getUnitId($unit){
        $unit = strtoupper($unit);
        if(DB::table('units')->where('code', $unit)->count()){
            return DB::table('units')->where('code', $unit)->first()->id;
        }else{
            return DB::table('units')->insertGetId([
                'name'  => $unit,
                'code' => $unit,
                'created_at'=> Carbon::now(),
                'updated_at'=> Carbon::now()
            ]);
        }
    }
    /**
     * Get Product Id
     */
    public function getProductId($data){

        // if(DB::table('products')->where('reference', $data[self::PRODUCT_REFERENCE])->count()){
        //     DB::table('products')->where('reference', $data[self::PRODUCT_REFERENCE])->update([
        //         'name'              => $data[self::PRODUCT_NAME],
        //         'wholesale_price'   => $data[self::PRODUCT_PRICE],
        //         'unit_id'           => $this->getUnitId($data[self::PRODUCT_UNIT]),
        //         'type'              => $data[self::PRODUCT_UNIT] == 'h' ? 'MO' : 'PRODUIT',
        //     ]);
        //     return DB::table('products')->where('reference', $data[self::PRODUCT_REFERENCE])->first()->id;
        // }else{
            return DB::table('products')->insertGetId([
                'affilie_id'    => 0,
                'name'          => $data[self::PRODUCT_NAME],
                'reference'     => $data[self::PRODUCT_REFERENCE],
                'unit_id'       => $this->getUnitId($data[self::PRODUCT_UNIT]),
                'wholesale_price'=> $data[self::PRODUCT_PRICE],
                'taxe_id'       => 2,
                'type'          => $data[self::PRODUCT_UNIT] == 'h' ? 'MO' : 'PRODUIT',
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now()
            ]);
        // }
    }

    /**
     * Import the ouvrages belongs to one group
     * @param ouvrageGroup
     */
    public function importOuvrageToDB($ouvrageGroup){
        $ouvrageId = 0;
        $taskName = '';
        $taskTextClient = '';
        $taskId = 0;
        $taskOrder = 1;
        if($ouvrageGroup[0][self::PRESTATION] == '' || $ouvrageGroup[0][self::TOIT] == '' || $ouvrageGroup[0][self::METIER] == '') return;
        foreach ($ouvrageGroup as $index => $data) {
            // ouvrage record
            if($index == 0){
                $ouvrageId = $this->insertGetOuvrageId($data);
                $taskName = $data[self::TASK_NAME];
                $taskTextClient = $data[self::TASK_TEXT_CLIENT];
                if($ouvrageGroup[$index+1][self::PRODUCT_UNIT] !=''){
                    $taskId = $this->insertGetTaskId($ouvrageId, $taskName, $taskTextClient, $data[self::TASK_TEXT_CHARGE], $taskOrder);
                }
                continue;
            }
            // task record
            if($index > 1 && $data[self::TASK_NAME] != ''){
                $taskName = $data[self::TASK_NAME];
                $taskTextClient = $data[self::TASK_TEXT_CLIENT];
                $taskOrder++;
                if(isset($ouvrageGroup[$index+1]) && $ouvrageGroup[$index+1][self::PRODUCT_UNIT] !=''){
                    $taskId = $this->insertGetTaskId($ouvrageId, $taskName, $taskTextClient, $data[self::TASK_TEXT_CHARGE], $taskOrder);
                }
                if( ! isset($ouvrageGroup[$index+1])){
                    $taskId = $this->skipOuvrage($ouvrageId, $data[self::CODELCDT]);
                }
                continue;
            }
             // task record
            if($data[self::TASK_TEXT_CHARGE] != '' && $data[self::PRODUCT_UNIT] == ''){
                $taskId = $this->insertGetTaskId($ouvrageId, $taskName, $taskTextClient, $data[self::TASK_TEXT_CHARGE], $taskOrder);
                continue;
            }
            // ouvrage detail record
            if($data[self::PRODUCT_PRICE] != 0){
                $this->insertOuvrageDetail($data, $taskId);
            }
        }
    }
    /**
     * Skip ouvrage and log
     *
     */
    public function skipOuvrage($ouvrageId, $lcdtCode){
        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/ouvrage_import.log'),
        ])->info('Incorrect Ouvrage'.PHP_EOL.'LCDTCODE: '.$lcdtCode);
        DB::table('ouvrages')->where('id', $ouvrageId)->delete();
        $tasks = DB::table('ouvrage_task')->where('ouvrage_id', $ouvrageId)->get();
        foreach ($tasks as $task) {
            $ouvrage_details = DB::table('ouvrage_detail')->where('ouvrage_task_id', $task->id)->get();
            foreach ($ouvrage_details as $detail) {
                DB::table('products')->where('id', $detail->product_id)->delete();
            }
            DB::table('ouvrage_detail')->where('ouvrage_task_id', $task->id)->delete();
        }
        DB::table('ouvrage_task')->where('ouvrage_id', $ouvrageId)->delete();
    }
    /**
     * Insert Ouvrage and return it's ID
     */
    public function insertGetOuvrageId($data){
        if(DB::table('ouvrages')->where('codelcdt', $data[self::CODELCDT])->count()){
            return DB::table('ouvrages')->where('codelcdt', $data[self::CODELCDT])->first()->id;
        }
        return DB::table('ouvrages')->insertGetId([
            'codelcdt'          => $data[self::CODELCDT],
            'textcustomer'      => $data[self::OUVRAGE_NAME],
            'textchargeaffaire' => $data[self::OUVRAGE_NAME],
            'ouvrage_toit_id'   => $this->getToitId($data[self::TOIT]),
            'ouvrage_metier_id' => $this->getMetierId($data[self::METIER]),
            'ouvrage_prestation_id'=> $this->getPrestationId($data[self::PRESTATION]),
            'unit_id'           => $this->getUnitId($data[self::OUVRAGE_UNIT]),
            'textoperator'      => '',
            'type'              => $this->getOuvrageType($data[self::PRESTATION]),
            'reference'         => $data[self::REFERENCE],
            'name'              => $data[self::OUVRAGE_NAME],
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now()
        ]);
    }

    /**
     * Insert task and return it's ID
     */
    public function insertGetTaskId($ouvrageId, $taskName, $customerText, $textChargeAffaire, $taskOrder){
        return DB::table('ouvrage_task')->insertGetId([
            'ouvrage_id'        => $ouvrageId,
            'order'             => $taskOrder,
            'name'              => $taskName,
            'textcustomer'      => $customerText,
            'textoperator'      => '',
            'textchargeaffaire' => $textChargeAffaire,
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now()
        ]);
    }

    /**
     * Insert ouvrage_detail and return it's ID
     */
    public function insertOuvrageDetail($data, $taskId){
        DB::table('ouvrage_detail')->insert([
            'ouvrage_task_id'   => $taskId,
            'product_id'        => $this->getProductId($data),
            'qty'               => $data[self::PRODUCT_UNIT] != 'h' ? $data[self::OUVRAGE_DETAIL_QTY] : 0,
            'numberh'           => $data[self::PRODUCT_UNIT] == 'h' ? $data[self::OUVRAGE_DETAIL_QTY] : 0,
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now()
        ]);
    }
}
