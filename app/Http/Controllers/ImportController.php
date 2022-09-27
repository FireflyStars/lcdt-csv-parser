<?php

namespace App\Http\Controllers;
use App\CSV\OuvrageParser;
use App\CSV\CustomerParser;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    public function importOuvrage(){
        ini_set('max_execution_time', 600);
        $pareser = new OuvrageParser;
        $ouvrages = $pareser->readFile(storage_path('app/ouvrage.csv'));

        $ouvrageGroup = [];
        $groupIdentifier = $ouvrages[0][OuvrageParser::CODE]; //init groupIdentifier
        foreach ($ouvrages as $ouvrage) {

            if($groupIdentifier == $ouvrage[OuvrageParser::CODE]){// if it has same groupIdentifier
                $ouvrageGroup[] = $ouvrage;
            }else{ // group changed
                // import to db
                $pareser->importOuvrageToDB($ouvrageGroup);
                // empty ouvrageGroup
                $ouvrageGroup = [];
                // add new ouvrage
                $ouvrageGroup[] = $ouvrage;
                $groupIdentifier = $ouvrage[OuvrageParser::CODE];
            }
        }
        $pareser->importOuvrageToDB($ouvrageGroup);
        dd("ouvrage import done");
    }

    public function importCustomer(){
        ini_set('max_execution_time', 6000);
        $pareser = new CustomerParser;
        $customers = $pareser->readFile(storage_path('app/client.csv'));
        foreach ($customers as $data) {
            $pareser->importToDB($data);
        }
        dd("customer import done");
    }

    public function importDetailingItem(){
        ini_set('max_execution_time', 6000);
        $typeItems = DB::table('typeitem')->select('name')->get()->map(function($value,$key){
            return collect($value)->values();
          })->collapse()->toArray();
        $newTypeItems = DB::table('infoitems')->whereNotIn('typeitem', $typeItems)->select('typeitem')->get()->unique();
        foreach ($newTypeItems as $key => $item) {
            DB::table('typeitemmatrix')->insert([
                'oldword'=> '',
                'newword'=> $item->typeitem,
            ]);
        }
        dd("done");
        return;
        $infoItems = DB::table('infoitems')->take(10)->get();
        foreach ($infoItems as $item) {
            if(DB::table('detailingitem_test')->where('tracking', $item->ItemTrackingKey)->count() == 0){
                $customerUUID = DB::table('infoInvoice')->where('InvoiceID', $item->InvoiceID)->value('CustomerID');
                $customerId = DB::table('infoCustomer')->where('CustomerID', $customerUUID)->value('id');
                $departmentId = DB::table('departments')->where('name', $item->DepartmentName)->value('id');
                $typeItem = DB::table('typeitem')->where('name', $item->typeitem)->select('id', 'category_id')->first();
                $brandId = DB::table('brands')->where('name', $item->brand)->value('id');
                $fabricIds = DB::table('fabrics')->where('name', $item->Fabrics)->select('id')->get()->map(function($value,$key){
                    return collect($value)->values();
                  })->collapse()->toJson();
                $colorIds = DB::table('colours')->where('name', $item->Colors)->select('id')->get()->map(function($value,$key){
                    return collect($value)->values();
                  })->collapse()->toJson();
                $complexitiesIds = DB::table('complexities')->where('name', $item->Complexities)->select('id')->get()->map(function($value,$key){
                    return collect($value)->values();
                  })->collapse()->toJson();
                $patternID = DB::table('patterns')->where('name', $item->Patterns)->value('id');
                $conditionID = DB::table('conditions')->where('name', $item->generalState)->value('id');
                $detailItemData = [
                    'item_id'   => 0,
                    'status'    => 'olditem',
                    'tracking'  => $item->ItemTrackingKey,
                    'order_id'  => 0,
                    'InvoiceID'  => $item->InvoiceID,
                    'etape'  => 11,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                    'pricecleaning'  => 0,
                    'coeftailoringbrand'  => 0,
                    'coefcleaningbrand'  => 0,
                    'coeftailoringfabric'  => 0,
                    'coefcleaningfabric'  => 0,
                    'coefcleaningcomplexities'  => 0,
                    'coeftailoringcomplexities'  => 0,
                    'cleaning_services'  => json_encode([1, 3]),
                    'tailoring_price_type'  => 'Standard',
                    'cleaning_price_type'  => 'Standard',
                    'tailoring_services'  => json_encode([]),
                    'cleaning_addon_price'  => 0,
                    'tailoring_price'  => 0,
                    'customer_id'  => $customerId,
                    'department_id'  => $departmentId,
                    'typeitem_id'  => $typeItem ? $typeItem->id : null,
                    'category_id'  => $typeItem ? $typeItem->category_id : null,
                    'brand_id'  => $brandId,
                    'fabric_id'  => $fabricIds,
                    'color_id'  => $colorIds,
                    'complexities_id'  => $complexitiesIds,
                    'pattern_id'  => $patternID,
                    'condition_id'  => $conditionID,
                ];
                DB::table('detailingitem_test')->insert($detailItemData);
            }
        }
        dd("done");
    }
}
