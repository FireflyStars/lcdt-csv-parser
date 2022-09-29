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
        ini_set('max_execution_time', 60000);
        // $brands = DB::table('brands')->select('name')->get()->map(function($value,$key){
        //     return collect($value)->values();
        //   })->collapse();
        // $neweBrands = DB::table('infoitems')->whereNotIn('brand', $brands)->select(DB::raw('COUNT(*) as cnt'), 'brand')->groupBy('brand')->get();
        // foreach ($neweBrands as $brand) {
        //     DB::table('brandmatrix')->insert([
        //         'oldword'=> '',
        //         'newword'=> $brand->brand,
        //         'nvofline'=> $brand->cnt,
        //     ]);
        // }
        // dd("done");
        DB::table('infoitems')->orderBy('id')->chunk(1000, function($infoItems){
            foreach ($infoItems as $item) {
                if(DB::table('detailingitem_copy')->where('tracking', $item->ItemTrackingKey)->count() == 0){
                    $typeItem = DB::table('typeitem')->where('name', $item->typeitem)->select('id', 'category_id', 'department_id')->first();
                    if($typeItem == null){
                        $oldName = DB::table('typeitemmatrix')->where('newword', $item->typeitem)->value('oldword');
                        if($oldName != ''){
                            $typeItem = DB::table('typeitem')->where('name', $oldName)->select('id', 'category_id', 'department_id')->first();
                        }else{
                            continue;
                        }
                    }
                    
                    if($item->brand == '' || $item->brand = 'a Brand Not Listed' || $item->brand = 'aaa unbranded' || $item->brand = 'Xxx Unbranded Xxx'){
                        $brandId = 3;
                    }else{
                        $brand = DB::table('brands')->where('name', $item->brand)->first();
                        if($brand == null){
                            $oldName = DB::table('brandmatrix')->where('newword', $item->brand)->value('oldword');
                            if($oldName != ''){
                                $brandId = DB::table('brands')->where('name', $oldName)->value('id');
                            }else{
                                $brandId = 3;
                            }
                        }
                    }
                    $customerUUID = DB::table('infoInvoice')->where('InvoiceID', $item->InvoiceID)->value('CustomerID');
                    $customerId = DB::table('infoCustomer')->where('CustomerID', $customerUUID)->value('id');
                    if($typeItem && $brandId && $customerId){
                        $fabricIds = DB::table('fabrics')->where('name', $item->Fabrics)->select('id')->get()->map(function($value,$key){
                            return collect($value)->values();
                            })->collapse()->toJson();
                        $colors = explode(", ", $item->Colors);
                        $colorIds = DB::table('colours')->whereIn('name', $colors)->select('id')->get()->map(function($value,$key){
                            return collect($value)->values();
                            })->collapse()->toJson();
                        $complexitiesIds = DB::table('complexities')->where('name', $item->Complexities)->select('id')->get()->map(function($value,$key){
                            return collect($value)->values();
                            })->collapse()->toJson();
                        $patternID = DB::table('patterns')->where('name', $item->Patterns)->value('id');
                        $conditionID = DB::table('conditions')->where('name', $item->generalState)->value('id');
                        $detailItemData = [
                            'item_id'   => $item->id,
                            'status'    => 'Completed',
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
                            'department_id'  => $typeItem->department_id,
                            'typeitem_id'  => $typeItem->id,
                            'category_id'  => $typeItem->category_id,
                            'brand_id'  => $brandId,
                            'fabric_id'  => $fabricIds,
                            'color_id'  => $colorIds,
                            'complexities_id'  => $complexitiesIds,
                            'pattern_id'  => $patternID,
                            'condition_id'  => $conditionID,
                        ];
                        DB::table('detailingitem_copy')->insert($detailItemData);
                    }
                }
            }
        });
        dd("done");
    }
}
