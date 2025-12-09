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
use App\Models\PemeriksaanTambahanLab;
use App\Models\View\VPemeriksaanDiagnosa;

class SatuSehatController extends Controller
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
                $antrianMasukAnamnesa = AntrianMasukRuangan::where('id_antrian', $kunjungan['id_antrian'])->where('keterangan', AntrianMasukRuangan::KETERANGAN_ANAMNESA)->first();
                $antrianMasukDokter = AntrianMasukRuangan::where('id_antrian', $kunjungan['id_antrian'])->where('keterangan', AntrianMasukRuangan::KETERANGAN_DOKTER)->first();
                $getLocation = RuanganPemeriksaan::where('id_ruangan_satusehat', $kunjungan->id_location)->first();
                if (empty($getLocation)) {
                    return response()->json(['ket' => 'no', 'message' => 'Lokasi is not found', 'status' => false]);
                }

                $location = [
                    'id_ruangan_satusehat' => $kunjungan->id_location,
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




                if (empty($satusehat_phases->service_request)) {
                    $pemeriskaanLab = PemeriksaanTambahanLab::with(['Petugas', 'PemeriksaanLab', 'PemeriksaanLab.DiagnosticReportCategory', 'DiagnosticReportConclusion',  'PemeriksaanLab.SatusehatSatuan', 'PemeriksaanLab.value_codeable_concept1_data', 'PemeriksaanLab.value_codeable_concept2_data', 'SampelLab', 'SampelLab.Snomed'])->where('id_kunjungan', $kunjungan->id)->get();
                    foreach ($pemeriskaanLab as $lab) {
                        if ($lab->jenis_nilai == "PAKET") {
                            // $rincianPaket =  RincianPaketLab::with('PemeriksaanLab')->where('id_paket', $lab->id)->get();
                            // foreach ($rincianPaket as $rincian) {
                            //     $serviceRequestLabId  =  $bundle->setServiceRequest($prefixEncounter . $satusehat_phases->id_encounter, $lab->id . "/" . $rincian->id,  $rincian, $dokter);

                            //     $specimenLabId = $bundle->setSpecimen($prefixEncounter . $satusehat_phases->id_encounter, $serviceRequestLabId, $lab->id, $rincian, $dokter);

                            //     $bundle->setDiagnosicReport($prefixEncounter . $satusehat_phases->id_encounter, $lab->id, $rincian, $specimenLabId, $lab->id, $rincian);
                            // }
                        } else {
                            $serviceRequestLabId  =  $bundle->setServiceRequest($prefixEncounter . $satusehat_phases->id_encounter, $lab->id, $lab, $dokter);
                            Log::info("lab_id: " . $lab->id);
                            $specimenLabId = $bundle->setSpecimen($prefixEncounter . $satusehat_phases->id_encounter, $serviceRequestLabId, $lab->id, $lab);

                            $observationLab = $bundle->setObservationLab($prefixEncounter . $satusehat_phases->id_encounter, $serviceRequestLabId,  $lab, $dokter, $specimenLabId);

                            $bundle->setDiagnosicReport($prefixEncounter . $satusehat_phases->id_encounter, $observationLab, $specimenLabId, $serviceRequestLabId, $lab, $dokter);
                        }
                    }

                    $satusehat_phases->service_request = 1;
                }
                //  END LAB

                $pemeriksaanTindakan = PemeriksaanTindakan::with(['icd9'])->where('id_kunjungan', $kunjungan->id)->where('id_icd9', '!=', 0)->get();
                $pemeriksaanTindakanIds = [];
                if (empty($satusehat_phases->procedure_medis)) {
                    foreach ($pemeriksaanTindakan as $procedure) {
                        if (empty($procedure->icd9)) continue;
                        $pemeriksaanTindakanIds[] = $procedure->id;
                        $bundle->setProcedure($prefixEncounter . $satusehat_phases->id_encounter, $dokter, $procedure);
                    }

                    $satusehat_phases->procedure_medis = true;
                }

                if (empty($satusehat_phases->condition_diagnosis)) {
                    foreach ($diagnosa as $penyakit) {
                        $bundle->setCondition($prefixEncounter . $satusehat_phases->id_encounter, $penyakit, $kunjungan->created);
                    }

                    $satusehat_phases->condition_diagnosis = true;
                }


                $resepObat = ResepObat::with('obat', 'obat.satusehat_kfa', 'obat.satusehat_medication_form', 'obat.satuan', 'route', 'satuan_dosis')->where('id_kunjungan', $kunjungan->id)->where('status', 'SELESAI')->get();
                $racikObat = Racik::with('obat', 'obat.obat', 'obat.obat.satuan', 'obat.obat.satusehat_kfa',  'satuan_obat', 'route', 'satuan_dosis', 'bentuk_sediaan', 'satusehat_medication_form')->where('id_kunjungan', $kunjungan->id)->where('status', 'SELESAI')->where('ket', '!=', 'DELETE')->get();


                $resepObatIds = [];
                $racikObatIds = [];
                if (empty($satusehat_phases->medication)) {
                    foreach ($resepObat as $resep) {

                        if (empty($resep->obat->satusehat_medication_form) || empty($resep->obat->satusehat_kfa) || empty($resep->signa1) || empty($resep->signa2) || empty($resep->signa_period) || empty($resep->total)) continue;
                        $resepObatIds[] = $resep->id;
                        $bundle->setMedicationPrescription($prefixEncounter . $satusehat_phases->id_encounter, $dokter, $resep);
                    }


                    foreach ($racikObat as $racik) {
                        echo json_encode([
                            "medication_form_code" => $racik->medication_form_code,
                            "signa1" => $racik->signa1,
                            "signa2" => $racik->signa2,
                            "signa_period" => $racik->signa_period,
                            "satusehat_route_id" => $racik->satusehat_route_id,
                            "obat" => $racik->obat,
                            "bentuk_sediaan" => $racik->bentuk_sediaan
                        ]);
                        die;

                        if (empty($racik->medication_form_code)  || empty($racik->signa1) || empty($racik->signa2) || empty($racik->signa_period) || empty($racik->satusehat_route_id) ||   empty($racik->obat) || empty($racik->bentuk_sediaan)) continue;
                        $racikObatIds[] = $racik->id;
                        $bundle->setMedicationPrescriptionMixed($prefixEncounter . $satusehat_phases->id_encounter, $dokter, $racik);
                    }
                }

                die;



                $result = $bundle->send($satusehat_phases->id_encounter);

                if (!empty($result['id_encounter'])) {
                    $kunjungan->id_encounter = $result['id_encounter'];
                    $kunjungan->save();

                    $satusehat_phases->id_encounter = $result['id_encounter'];
                    $satusehat_phases->save();

                    // CatatanPasien::whereIn('id', $patientNoteIds)->update(['satusehat_status' => true]);
                    PemeriksaanTindakan::whereIn('id', $pemeriksaanTindakanIds)->update(['satusehat_status' => true]);
                    // ResepObat::whereIn('id', $resepObatIds)->update(['satusehat_status' => true]);
                    // Racik::whereIn('id', $racikObatIds)->update(['satusehat_status' => true]);

                    // return response()->json($result);
                    return response()->json(['ket' => 'no', 'message' => 'data terkirim', 'status' => true]);
                } else {
                    return response()->json(['ket' => 'no', 'message' => 'data tidak terkirim', 'status' => false]);
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
                'message' => 'Terjadi kesalahan saat generate data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
