<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Racik;
use GuzzleHttp\Client;
use App\Jobs\bundleBatch;
use App\Models\Kunjungan;
use App\Models\RacikObat;
use App\Models\ResepObat;
use App\Models\Perusahaan;
use App\Models\Pendaftaran;
use Illuminate\Http\Request;
use App\Models\CatatanPasien;
use App\Models\SatusehatPhase;
use Satusehat\Integration\KYC;
use App\Models\SatusehatAllergy;
use App\Models\RuanganPemeriksaan;
use App\Services\Satusehat\Bundle;
use PhpParser\Node\Stmt\Continue_;
use App\Models\AntrianMasukRuangan;
use App\Models\PemeriksaanTindakan;
use Illuminate\Support\Facades\Log;
use App\Models\View\VPemeriksaanDiagnosa;

class SatuSehatPribadiController extends Controller
{
    public function bundle($visitId)
    {
        $kunjungan = Kunjungan::where('id', $visitId)->first();
        $pendaftaran = Pendaftaran::where('id', $kunjungan['id_pendaftaran'])->first();

        if (!empty($pendaftaran['id_pasien_satusehat'])) {
            $prefixEncounter = "urn:uuid:";
            $satusehat_phases = SatusehatPhase::where('id_encounter', $kunjungan['id_encounter'])->firstOrNew();
            if (empty($satusehat_phases->id_encounter) && !empty($kunjungan['id_encounter'])) {
                $prefixEncounter = "Encounter/";
                $satusehat_phases->id_encounter = $kunjungan['id_encounter'];
            }

            try {
                $bundle = new Bundle;
                $dokter = User::where('id', $kunjungan->id_dokter)->first();
                $kunjungan = Kunjungan::where('id', $kunjungan->id)->first();
                // Log::info("dokterssss", ['doktersss' => $dokter]);
                // Log::info("kunjungan", ['kunjungan' => $kunjungan]);
                $antrianMasukAnamnesa = AntrianMasukRuangan::where('id_antrian', $kunjungan['id_antrian'])->where('keterangan', AntrianMasukRuangan::KETERANGAN_ANAMNESA)->first();
                $antrianMasukDokter = AntrianMasukRuangan::where('id_antrian', $kunjungan['id_antrian'])->where('keterangan', AntrianMasukRuangan::KETERANGAN_DOKTER)->first();
                $getLocation = RuanganPemeriksaan::where('id_dokter', $dokter->id)->first();

                if (empty($getLocation)) {
                    return response()->json(['ket' => 'no', 'message' => 'Lokasi is not found', 'status' => false]);
                }

                $location = [
                    'id_ruangan_satusehat' => $getLocation->id_ruangan_satusehat,
                    'nama_ruangan' => $getLocation->nama . ' Ruangan ' . $getLocation->ruangan
                ];

                $diagnosa = VPemeriksaanDiagnosa::where('id_kunjungan', $kunjungan->id)->get();

                $bundle->setSubject($pendaftaran);
                if (empty($satusehat_phases->id_encounter)) {
                    $satusehat_phases->id_encounter = $bundle->setEncounterAmbulatory($kunjungan->ucode, $dokter, $antrianMasukAnamnesa->created, $antrianMasukAnamnesa->updated, $antrianMasukDokter->created, $antrianMasukDokter->updated, $location);
                }

                // $patientNote = CatatanPasien::with('SatusehatAllergy')
                //     ->where('satusehat_status', 0)
                //     ->where('satusehat_code', '!=', "")
                //     ->where('jenis', '!=', 'penyakit-lama')
                //     ->where('jenis', '!=', 'penyakit-keluarga')
                //     ->where('id_pendaftaran', $pendaftaran->id)
                //     ->where('ket', '!=', 'DELETE')
                //     ->get();


                // $patientNoteIds = [];
                // if (empty($satusehat_phases->allergy)) {
                //     $patientAllergies = $patientNote->pluck('satusehat_code');
                //     $allergies = SatusehatAllergy::whereIn('code', $patientAllergies)->get()->keyBy('code');
                //     foreach ($patientNote as $note) {
                //         $note->satusehat = $allergies[$note['satusehat_code']];
                //         $patientNoteIds[] = $sanote->id;

                //         $bundle->setAllergyIntolerance($prefixEncounter . $satusehat_phases->id_encounter, $note, $dokter);
                //     }

                //     $satusehat_phases->allergy = true;
                // }


                if (empty($satusehat_phases->observation_pemeriksaan_fisik) || !($satusehat_phases->observation_pemeriksaan_fisik ?? false)) {
                    $bundle->setObservation($prefixEncounter . $satusehat_phases->id_encounter, 'sistole', $kunjungan->sistole, $dokter, $kunjungan->created, $kunjungan->created);
                    $bundle->setObservation($prefixEncounter . $satusehat_phases->id_encounter, 'diastole', $kunjungan->diastole, $dokter, $kunjungan->created, $kunjungan->created);
                    $bundle->setObservation($prefixEncounter . $satusehat_phases->id_encounter, 'respiratory', $kunjungan->resdiratory_rate, $dokter, $kunjungan->created, $kunjungan->created);
                    $bundle->setObservation($prefixEncounter . $satusehat_phases->id_encounter, 'heart_rate', $kunjungan->heart_rate, $dokter, $kunjungan->created, $kunjungan->created);
                    $bundle->setObservation($prefixEncounter . $satusehat_phases->id_encounter, 'temprature', $kunjungan->suhu_badan, $dokter, $kunjungan->created, $kunjungan->created);

                    $satusehat_phases->observation_pemeriksaan_fisik = true;
                }

                // // Fix me: LAB / Rujuk (Need to ask teams to make sure the existing flows)
                // if (empty($satusehat_phases->service_request)) {
                // }

                // if (empty($satusehat_phases->specimen)) {
                // }

                // if (empty($satusehat_phases->observation_hasil_pemeriksaan_penunjang)) {
                // }
                // // END LAB

                // $pemeriksaanTindakan = PemeriksaanTindakan::with(['icd9'])->where('id_kunjungan', $kunjungan->id)->where('id_icd9', '!=', 0)->get();
                // $pemeriksaanTindakanIds = [];
                // if (empty($satusehat_phases->procedure_medis)) {
                //     foreach ($pemeriksaanTindakan as $procedure) {
                //         if (empty($procedure->icd9)) continue;
                //         $pemeriksaanTindakanIds[] = $procedure->id;
                //         $bundle->setProcedure($prefixEncounter . $satusehat_phases->id_encounter, $dokter, $procedure);
                //     }

                //     $satusehat_phases->procedure_medis = true;
                // }

                if (empty($satusehat_phases->condition_diagnosis)) {
                    foreach ($diagnosa as $penyakit) {
                        $bundle->setCondition($prefixEncounter . $satusehat_phases->id_encounter, $penyakit, $kunjungan->created);
                    }

                    $satusehat_phases->condition_diagnosis = true;
                }

                // $resepObat = ResepObat::with('obat', 'obat.satuan', 'medication_form', 'route', 'satuan_dosis')->where('id_kunjungan', $kunjungan->id)->get();

                // $resepObat = ResepObat::where('id_kunjungan', $kunjungan->id)->get();
                // $racikObat = Racik::with('obat')->where('id_kunjungan', $kunjungan->id)->get();
                // $resepObatIds = [];
                // $racikObatIds = [];
                // if (empty($satusehat_phases->medication)) {
                //     foreach ($resepObat as $resep) {
                //         // var_dump($resep);
                //         // die;
                //         $dataObat = $this->getDataObat($resep->id_obat, $resep->medication_form_code, $resep->satusehat_route_code, $resep->id_satuan_dosis);
                //         // return json_encode($dataObat);
                //         $dataObat['resep'] = $resep;


                //         if (count($dataObat['obat']) > 0 && count($dataObat['medication']) > 0 && count($dataObat['route']) > 0 && count($dataObat['dosis']) > 0) {
                //             $resepObatIds[] = $resep->id;
                //             $bundle->setMedicationPrescription($prefixEncounter . $satusehat_phases->id_encounter, $dokter, $dataObat);
                //         }
                //     }


                //     foreach ($racikObat as $racik) {
                //         $dataObatRacik = $this->getDataObat("", $racik->medication_form_code, $racik->satusehat_route_code, $racik->id_satuan_dosis);
                //         $dataObatRacik['racik'] = $racik;
                //         // dd($dataObatRacik);

                //         if (count($dataObatRacik['medication']) > 0 && count($dataObatRacik['dosis'])) {
                //             $racikObatIds[] = $racik->id;
                //             $bundle->setMedicationPrescriptionMixed($prefixEncounter . $satusehat_phases->id_encounter, $dokter, $dataObatRacik);
                //         }
                //     }
                // }

                // echo json_encode($bundle);
                // die;



                $result = $bundle->sendPribadi($satusehat_phases->id_encounter, $dokter->id);
                // Log::info('result', ['return_result' => $result]);


                if (!empty($result['id_encounter'])) {
                    $kunjungan->id_encounter = $result['id_encounter'];
                    $kunjungan->save();

                    $satusehat_phases->id_encounter = $result['id_encounter'];
                    $satusehat_phases->save();

                    // CatatanPasien::whereIn('id', $patientNoteIds)->update(['satusehat_status' => true]);
                    // PemeriksaanTindakan::whereIn('id', $pemeriksaanTindakanIds)->update(['satusehat_status' => true]);
                    // ResepObat::whereIn('id', $resepObatIds)->update(['satusehat_status' => true]);
                    // Racik::whereIn('id', $racikObatIds)->update(['satusehat_status' => true]);

                    // return response()->json($result);
                    return response()->json(['ket' => 'no', 'message' => 'data terkirim', 'status' => true]);
                } else {
                    return response()->json(['ket' => 'no', 'message' => 'data tidak terkirim', 'status' => false, 'result' => $result]);
                }
            } catch (\Exception $e) {
                throw $e;
                return response()->json(['ket' => 'no', 'message' => $e->getMessage(), 'status' => false]);
            }
        } else {
            return response()->json(['ket' => 'no', 'message' => 'Pasien belum terverifikasi satu sehat', 'status' => false]);
        }
    }

    public function bundleBatch(Request $request)
    {
        $start = $request->start;
        $end = $request->end;
        $kunjungans = Kunjungan::whereBetween('tanggal', [$start, $end])->get();
        foreach ($kunjungans as $kunjungan) {
            dispatch(new bundleBatch($kunjungan));
        }


        return response()->json(['ket' => 'yes', 'message' => 'proses pengiriman selesai', 'status' => true]);
    }


    public function getDataObat($id_obat = "", $medication_code = "", $route_code = "", $id_satuan_dosis = "")
    {
        try {
            $client = new Client();
            $url = env('KESTURI_BASE_URL') . 'klinik_api/satusehat/get_obat_satusehat_kesturi';

            $response = $client->post($url, [
                'json' => [
                    'id_obat' => $id_obat,
                    'medication_code' => $medication_code,
                    'route_code' => $route_code,
                    'id_satuan_dosis' => $id_satuan_dosis
                ]
            ]);

            $response_string = $response->getBody()->getContents();
            $response_array = json_decode($response_string, true);
            $data = $response_array['data'];
            return $data;
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function testGetRacik($id_obat = '', $id_satuan = '')
    {
        $racik = Racik::with('obat')->where('id_kunjungan', 522666)->get();
        $dataRacik = [];
        foreach ($racik as $racik) {
            $racikObats = $racik->obat;
            foreach ($racikObats as $racikObat) {
                $dataRacik[] =  $this->getDataObatApi($racikObat->id_obat);
            }
        }

        // echo json_encode($dataRacik);
        // die;
    }

    public function getDataObatApi($id_obat = "")
    {
        try {
            $client = new Client();
            $url = env('KESTURI_BASE_URL') . 'klinik_api/satusehat/get_obat_detail_satusehat_kesturi';

            $response = $client->post($url, [
                'json' => [
                    'id_obat' => $id_obat,

                ]
            ]);

            $response_string = $response->getBody()->getContents();
            $response_array = json_decode($response_string, true);
            return $response_array['data'];
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function getKycLink(Request $request)
    {
        $nama = $request->nama;
        $nik = $request->nik;

        $kyc = new KYC;
        try {

            $json = $kyc->generateUrl($nama, $nik);
            $kyc_link = json_decode($json, true);
            $dataKyc = [
                'url' => $kyc_link['data']['url'],
                'message' => 'success'
            ];

            if (!empty($dataKyc['data']['url'])) {
                return response()->json(['message' => 'Data URL is empty or not set'], 404);
            }

            return response()->json($dataKyc);
        } catch (\Exception $e) {
            // Log the error message and stack trace
            Log::error('Error generating URL: ' . $e->getMessage(), ['exception' => $e]);

            // Return a detailed error response
            return response()->json([
                'message' => 'Terjadi kesalahan  saat generate data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLocation() {}

    public function createLocation(Request $request)
    {
        $body_satusehat = $request->input('body_satusehat');
        $id_dokter = $request->input('id_dokter');
        $data_ruangan = $request->input('data_ruangan');

        if ($body_satusehat && $id_dokter && $data_ruangan) {

            $bundle = new Bundle;
            $result =  $bundle->locationCreate($body_satusehat, $id_dokter, $data_ruangan);

            return response()->json($result);
        } else {
            return response()->json(['message' => 'Invalid input format. Expected a string for body_satusehat.'], 400);
        }

        // Sekarang $data pasti array
    }

    public function updateLocation() {}

    public function deleteLocation() {}
}
