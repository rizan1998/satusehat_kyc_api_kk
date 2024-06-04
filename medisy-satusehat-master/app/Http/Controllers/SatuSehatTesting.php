<?php

namespace App\Http\Controllers;

use App\Models\Perusahaan;
use Satusehat\Integration\KYC;
// use App\Services\Satusehat\KYC;



class SatuSehatTesting extends Controller
{
    public function index()
    {
        // $kyc = new KYC;

        // $json = $kyc->generateUrl('Bambang Wisanggeni', '1171022809990001');
        // $kyc_link = json_decode($json, true);

        // return redirect($kyc_link['data']['url']);


        $kyc = new KYC;

        // Isi nama verifikator & NIK verifikator untuk mendapatkan link KYC
        try {

            $json = $kyc->generateUrl('', '');
            $kyc_link = json_decode($json, true);
            $dataKyc = [
                'url' => $kyc_link['data']['url'],
                'message' => 'success'
            ];

            if (empty($dataKyc['data']['url'])) {
                return response()->json(['message' => 'Data URL is empty or not set'], 404);
            }

            return response()->json($dataKyc);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan saat generate data'], 500);
        }

        // return redirect($kyc_link['data']['url']);
    }
}
