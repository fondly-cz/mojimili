<?php

namespace App\Http\Controllers;

use Fondly\Ares\Ares;
use Exception;
use Illuminate\Http\Request;

class AresController extends Controller
{
    public function getCompanyData(Request $request)
    {
        $request->validate([
            'ico' => 'required|string',
        ]);

        try {
            $ares = Ares::create();
            $rzp = $ares->getRzp();
            $subject = $rzp->getByIn($request->ico);

            $address = $subject->addresses[0] ?? null;
            $street = '';

            if ($address) {
                // If there is no street name, use municipality part name
                $streetName = $address->streetName ?: $address->municipalityPartName;
                if ($streetName) {
                    $street .= $streetName;
                }

                if ($address->buildingNumber) {
                    $street .= ' '.$address->buildingNumber;
                }
                if ($address->orientationNumber) {
                    $street .= '/'.$address->orientationNumber;
                }
            }

            return response()->json([
                'name' => $subject->tradeName,
                'ico' => $subject->in,
                'dic' => 'CZ'.$subject->in, // Většina plátců DPH má DIČ CZ + IČO
                'address' => trim($street),
                'city' => $address ? $address->municipalityName : '',
                'state' => $address ? $address->districtName : '',
                'postal_code' => $address ? $address->zipCode : '',
                'country' => $address ? $address->stateName : '',
                'industry' => '',
            ]);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
