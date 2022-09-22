<?php

namespace App\Http\Controllers;
use App\CSV\OuvrageParser;
use App\CSV\CustomerParser;

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
}
