<?php

namespace App\Services\Satusehat;

use DateTime;
use DateTimeZone;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use App\Models\Perusahaan;
use App\Models\Satusehat_kfa;
use GuzzleHttp\Psr7\Request;
use App\Models\CatatanPasien;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ClientException;

class Bundle
{
    public $organizationID, $patientReference, $patientDisplay, $bundleEntry, $locationRoom, $medicationUUID;

    public function __construct()
    {
        $perusahaan = Perusahaan::first();
        $this->organizationID = $perusahaan->organization_id;
    }

    public function setSubject($pasien)
    {
        $this->patientReference = 'Patient/' . $pasien->id_pasien_satusehat;
        $this->patientDisplay = $pasien->nama;
    }

    public function setEncounterAmbulatory($kunjungan_id, $dokter, $anamnesisStart, $anamnesisEnd, $pemeriksaanStart, $pemeriksaanEnd, $location)
    {

        if ($dokter->satusehat_mode == 'pribadi') {
            $this->organizationID = $dokter->organization_id;
        }

        $id = Uuid::uuid4()->toString();
        $this->bundleEntry['title_payload'][] =  'Create Encounter';
        $this->bundleEntry['resource'][] = [
            "fullUrl" => "urn:uuid:" . $id,
            "resource" => [
                'resourceType' => 'Encounter',
                'status'          => 'finished',
                'identifier' => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/encounter/" . $this->organizationID,
                        "value"  => $kunjungan_id
                    ]
                ],
                'subject' => [
                    'reference' => $this->patientReference,
                    'display'   => $this->patientDisplay,
                ],
                'participant' => [
                    [
                        "type" => [
                            [
                                "coding" => [
                                    [
                                        "system"  => "http://terminology.hl7.org/CodeSystem/v3-ParticipationType",
                                        "code"    => "ATND",
                                        "display" => "attender"
                                    ]
                                ]
                            ]
                        ],
                        "individual" => [
                            'reference' => 'Practitioner/' . $dokter->id_dokter_satusehat,
                            'display'   => $dokter->nama_lengkap,
                        ]
                    ]
                ],
                'period' => [
                    "start" => $this->formattedDate($anamnesisStart),
                    "end" => $this->formattedDate($pemeriksaanEnd),
                ],
                'location' => [
                    [
                        'location' => [
                            "reference" => "Location/" . $location['id_ruangan_satusehat'],
                            "display"   => $location['nama_ruangan']
                        ]
                    ]
                ],
                'statusHistory' =>  [
                    [
                        "status" => "arrived",
                        "period" => [
                            "start" => $this->formattedDate($anamnesisStart),
                            'end'   => $this->formattedDate($anamnesisEnd)
                        ]
                    ],
                    [
                        "status" => "in-progress",
                        "period" => [
                            "start" => $this->formattedDate($anamnesisEnd),
                            "end" => $this->formattedDate($pemeriksaanEnd),
                        ]
                    ],
                    [
                        "status" => "finished",
                        "period" => [
                            "start" => $this->formattedDate($pemeriksaanEnd),
                            "end" => $this->formattedDate($pemeriksaanEnd),
                        ]
                    ]
                ],
                'serviceProvider' => [
                    'reference' => 'Organization/' . $this->organizationID
                ],
                'class' => [
                    'system'  => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                    'code'    => 'AMB',
                    'display' => 'ambulatory'
                ]
            ],
            "request" => [
                "method" => "POST",
                "url" => "Encounter"
            ]
        ];

        return $id;
    }

    public function setAllergyIntolerance($idEncounter, $patient_note, $dokter)
    {
        // dd($patient_note->SatusehatAllergy);
        $uuid = Uuid::uuid4()->toString();
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

        if ($patient_note->jenis === CatatanPasien::JENIS['alergi-obat']) {
            $coding = [
                [
                    "system"  => "http://sys-ids.kemkes.go.id/kfa",
                    "code"    => $patient_note->satusehat_code,
                    "display" => $patient_note->nama,
                ]
            ];
        } else {
            $coding = [
                [
                    "system"  => $patient_note->SatusehatAllergy->codesystem,
                    "code"    => $patient_note->SatusehatAllergy->code,
                    "display" => $patient_note->SatusehatAllergy->display,
                ]
            ];
        }

        $this->bundleEntry['title_payload'][] = $patient_note->jenis;
        $this->bundleEntry['resource'][] = [
            "fullUrl" => "urn:uuid:" . $uuid,
            "resource" => [
                'resourceType' => 'AllergyIntolerance',
                'identifier' => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/allergy/" . $this->organizationID,
                        "use" => "official",
                        "value" => $patient_note->id,
                    ]
                ],
                'category' => [CatatanPasien::JENIS[$patient_note->jenis]],
                'code' =>  [
                    'coding' => $coding,
                ],
                "patient" => [
                    "reference" => $this->patientReference,
                    "display" => $this->patientDisplay
                ],
                "encounter" => [
                    "reference" => $idEncounter,
                    "display" => "Kunjungan " . $this->patientDisplay,
                ],
                "recorder" => [
                    'reference' => 'Practitioner/' . $dokter->id_dokter_satusehat,
                ]
            ],
            "request" => ["method" => "POST", "url" => "Condition"]
        ];
    }

    public function setObservation($idEncounter, $kategori, $hasil, $dokter, $kunjunganTanggal, $tanggal)
    {
        $uuid = Uuid::uuid4()->toString();
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before set observation");

        $coding        = [];
        $valueQuantity = [];

        if ($kategori == 'sistole') {
            $coding = [
                [
                    "system"  => "http://loinc.org",
                    "code"    => "8480-6",
                    "display" => "Systolic blood pressure"
                ]
            ];
            $valueQuantity = [
                "value"  => floatval($hasil),
                "unit"   => "mm[Hg]",
                "system" => "http://unitsofmeasure.org",
                "code"   => "mm[Hg]"
            ];
        } else if ($kategori == 'diastole') {
            $coding = [
                [
                    "system"  => "http://loinc.org",
                    "code"    => "8462-4",
                    "display" => "Diastolic blood pressure"
                ]
            ];
            $valueQuantity = [
                "value"  => floatval($hasil),
                "unit"   => "mm[Hg]",
                "system" => "http://unitsofmeasure.org",
                "code"   => "mm[Hg]"
            ];
        } else if ($kategori == 'respiratory') {
            $coding = [
                [
                    "system"  => "http://loinc.org",
                    "code"    => "9279-1",
                    "display" => "Respiratory rate"
                ]
            ];
            $valueQuantity = [
                "value"  => floatval($hasil),
                "unit"   => "breaths/minute",
                "system" => "http://unitsofmeasure.org",
                "code"   => "/min"
            ];
        } else if ($kategori == 'heart_rate') {
            $coding = [
                [
                    "system"  => "http://loinc.org",
                    "code"    => "8867-4",
                    "display" => "Heart rate"
                ]
            ];
            $valueQuantity = [
                "value"  => floatval($hasil),
                "unit"   => "beats/minute",
                "system" => "http://unitsofmeasure.org",
                "code"   => "/min"
            ];
        } else if ($kategori == 'temprature') {
            $coding = [
                [
                    "system"  => "http://loinc.org",
                    "code"    => "8310-5",
                    "display" => "Body temperature"
                ]
            ];
            $valueQuantity = [
                "value"  => floatval($hasil),
                "unit"   => "C",
                "system" => "http://unitsofmeasure.org",
                "code"   => "Cel"
            ];
        }
        $this->bundleEntry['title_payload'][] = 'observation';
        $this->bundleEntry['resource'][] = [
            "fullUrl" => "urn:uuid:" . $uuid,
            "resource" => [
                'resourceType' => 'Observation',
                'status'          => 'final',
                "category" => [
                    [
                        "coding" => [
                            [
                                "system"  => "http://terminology.hl7.org/CodeSystem/observation-category",
                                "code"    => "vital-signs",
                                "display" => "Vital Signs"
                            ]
                        ]
                    ]
                ],
                'code' => [
                    "coding" => $coding
                ],
                'subject' => [
                    "reference" => $this->patientReference
                ],
                'performer' => [
                    [
                        "reference" => "Practitioner/" . $dokter->id_dokter_satusehat
                    ]
                ],
                'encounter' => [
                    "reference" => $idEncounter,
                    "display"   => "Pemeriksaan anamnesa " . $this->patientDisplay . " " . $kunjunganTanggal
                ],
                'effectiveDateTime' => $this->formattedDate($tanggal),
                'valueQuantity' => $valueQuantity,
            ],
            "request" => ["method" => "POST", "url" => "Observation"]
        ];
    }

    public function setServiceRequest($idEncounter, $id, $dataLab, $dokter)
    {
        $pemeriksaanLab = $dataLab->PemeriksaanLab;
        $petugas = $dataLab->Petugas;
        if (empty($petugas) || empty($petugas->id_dokter_satusehat)) {
            $petugas = $dokter;
        }

        if ($idEncounter == null) throw new \Exception("Please insert encounter before set observation");
        $uuid = Uuid::uuid4()->toString();

        $this->bundleEntry['title_payload'][] = 'lab';
        $this->bundleEntry['resource'][] = [
            'fullUrl' => "urn:uuid:" . $uuid,
            'resource' =>  [
                "resourceType" => "ServiceRequest",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/servicerequest/" . $this->organizationID,
                        "value" => (string) $id
                    ]
                ],
                "status" => "active",
                "intent" => "original-order",
                "priority" => "routine",
                "category" => [
                    [
                        "coding" => [
                            [
                                "system" => "http://snomed.info/sct",
                                "code" => "108252007",
                                "display" => "Laboratory procedure"
                            ]
                        ]
                    ]
                ],
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => $pemeriksaanLab->Code,
                            "display" => $pemeriksaanLab->code_display
                        ]
                    ],
                    "text" => $pemeriksaanLab->nama
                ],
                "subject" => [
                    "reference" => $this->patientReference
                ],
                "encounter" => [
                    "reference" => $idEncounter,
                    "display" => "Permintaan Pemeriksaan Lab " . $this->patientDisplay . " pada tanggal " . $dataLab->created
                ],
                "occurrenceDateTime" => $this->formattedDate($dataLab->created),
                "authoredOn" => $this->formattedDate($dataLab->created),
                "requester" => [
                    "reference" => "Practitioner/" . $dokter->id_dokter_satusehat,
                    "display" => $dokter->nama_lengkap
                ],
                "performer" => [
                    [
                        "reference" => "Practitioner/" . $petugas->id_dokter_satusehat,
                        "display" => $petugas->nama_lengkap
                    ]
                ]
            ],
            "request" => ["method" => "POST", "url" => "ServiceRequest"]
        ];

        return $uuid;
    }

    public function setSpecimen($idEncounter, $serviceRequestLabId, $id, $dataLab)
    {
        if ($idEncounter == null) throw new \Exception("Please insert encounter before set observation");
        $uuid = Uuid::uuid4()->toString();

        $this->bundleEntry['title_payload'][] = 'specimen';
        $this->bundleEntry['resource'][] = [
            'fullUrl' => "urn:uuid:" . $uuid,
            'resource' =>  [
                "resourceType" => "Specimen",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/specimen/" . $this->organizationID,
                        "value" => (string) $id
                    ]
                ],
                "status" => "available",
                "type" => [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => $dataLab->SampelLab->code,
                            "display" => $dataLab->SampelLab->Snomed->term
                        ]
                    ]
                ],
                "collection" => [
                    "collectedDateTime" => $this->formattedDate((new Carbon($dataLab->jam_ambil_sample))->format("Y-m-d H:i:s")),
                ],
                "subject" => [
                    "reference" => $this->patientReference,
                    "display" => $this->patientDisplay
                ],
                "request" => [
                    [
                        "reference" => "ServiceRequest/" . $serviceRequestLabId,
                    ],
                ],
            ],
            'request' => ['method' => 'POST', 'url' => 'Specimen']
        ];
        return $uuid;
    }

    public function setObservationLab($idEncounter, $serviceRequestLabId, $dataLab, $dokter, $specimenLabId)
    {
        if ($idEncounter == null) throw new \Exception("Please insert encounter before set observation");
        $uuid = Uuid::uuid4()->toString();
        $id_kategori =  $dataLab->PemeriksaanLab->kategori_loinc_id;

        $coding = [];
        if ($id_kategori ==  1) { //jika mikrobiologi
            $loinc = DB::table('loinc_lab_mikrobiologi_klinik')->where('Code', $dataLab->PemeriksaanLab->Code)->first();
            $coding = [
                [
                    "system" => $loinc->Code_System,
                    "code" => $loinc->Code,
                    "display" => $loinc->Display
                ]
            ];
        } else if ($id_kategori == 2) { //jika kimia klinik
            $loinc = DB::table('loinc_lab_patologi_anatomi')->where('Kode', $dataLab->PemeriksaanLab->Code)->first();
            $coding = [
                [
                    "system" => $loinc->Code_System,
                    "code" => $loinc->Kode,
                    "display" => $loinc->Nama_Pemeriksaan
                ]
            ];
        } else if ($id_kategori == 3) { //jika kimia klinik
            $loinc = DB::table('loinc_lab_patologi_klinik')->where('code', $dataLab->PemeriksaanLab->Code)->first();
            $coding = [
                [
                    "system" => $loinc->code_system,
                    "code" => $loinc->code,
                    "display" => $loinc->display
                ]
            ];
        } else {
            return; // Jika kategori tidak dikenali, tidak melakukan apa-apa
        }

        $resource = [
            "resourceType" => "Observation",
            "basedOn" => [
                [
                    "reference" => "ServiceRequest/" . $serviceRequestLabId
                ]
            ],
            "status" => "final",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/observation-category",
                            "code" => "laboratory",
                            "display" => "Laboratory"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => $coding
            ],
            "performer" => [
                [
                    "reference" => "Practitioner/" . $dokter->id_dokter_satusehat,
                ]
            ],
            "subject" => [
                "reference" => $this->patientReference,
                "display" => $this->patientDisplay
            ],
            "encounter" => [
                "reference" => $idEncounter
            ],
            "effectiveDateTime" => $this->formattedDate($dataLab->created_at),
            "issued" => $this->formattedDate($dataLab->created_at),
            "specimen" => [
                "reference" => "Specimen/" . $specimenLabId
            ]
        ];


        if ($dataLab->PemeriksaanLab->jenis_nilai != 'PAKET') {
            if ($dataLab->PemeriksaanLab->jenis_nilai == 'NUMBER') {
                $resource['valueQuantity'] = [
                    "value" => (float)$dataLab->hasil,
                    "unit" => $dataLab->PemeriksaanLab->SatusehatSatuan->unit,
                    "system" => $dataLab->PemeriksaanLab->SatusehatSatuan->codesystem,
                    "code" => $dataLab->PemeriksaanLab->SatusehatSatuan->code,
                ];
            } else if ($dataLab->PemeriksaanLab->jenis_nilai == 'STATEMENT') {
                if ($dataLab->hasil == $dataLab->PemeriksaanLab->nilai_1) {
                    $resource['valueCodeableConcept'] =   [
                        "coding" => [
                            [
                                "system" => $dataLab->PemeriksaanLab->value_codeable_concept1_data->system,
                                "code" => $dataLab->PemeriksaanLab->value_codeable_concept1_data->code,
                                "display" => $dataLab->PemeriksaanLab->value_codeable_concept1_data->display
                            ]
                        ]
                    ];
                } else {
                    $resource['valueCodeableConcept'] =   [
                        "coding" => [
                            [
                                "system" => $dataLab->PemeriksaanLab->value_codeable_concept2_data->system,
                                "code" => $dataLab->PemeriksaanLab->value_codeable_concept2_data->code,
                                "display" => $dataLab->PemeriksaanLab->value_codeable_concept2_data->display
                            ]
                        ]
                    ];
                }
            } else if ($dataLab->PemeriksaanLab->jenis_nilai == 'MULTIPLE') {
            } else if ($dataLab->PemeriksaanLab->jenis_nilai == 'LIST MULTIPLE') {
            }
        }


        $this->bundleEntry['title_payload'][] = 'observation Lab';
        $this->bundleEntry['resource'][] = [
            'fullUrl' => "urn:uuid:" . $uuid,
            'resource' =>  $resource,
            'request' => ['method' => 'POST', 'url' => 'Observation']
        ];
        return $uuid;


        // $this->bundleEntry[] = [
        //     'fullUrl' => "urn:uuid:" . $uuid,
        //     'resource' =>   [
        //         "resourceType" => "Observation",
        //         "status" => "final",
        //         "basedOn" => [
        //             [
        //                 "reference" => "ServiceRequest/" . $serviceRequestLabId
        //             ]
        //         ],
        //         "category" => [
        //             [
        //                 "coding" => [
        //                     [
        //                         "system" => "http://terminology.hl7.org/CodeSystem/observation-category",
        //                         "code" => "laboratory",
        //                         "display" => "Laboratory"
        //                     ]
        //                 ]
        //             ]
        //         ],
        //         "code" => [
        //             "coding" => [
        //                 [
        //                     "system" => $dataLab->PemeriksaanLab->Code_System,
        //                     "code" => "3084-1",
        //                     "display" => "Urate [Mass/volume] in Serum or Plasma"
        //                 ]
        //             ]
        //         ],
        //         "performer" => [
        //             [
        //                 "reference" => "Practitioner/N10000001"
        //             ]
        //         ],
        //         "subject" => [
        //             "reference" => "Patient/{{Patient_Id}}",
        //             "display" => "{{Patient_Name}}"
        //         ],
        //         "specimen" => [
        //             "reference" => "Specimen/{{Specimen_Urat}}"
        //         ],
        //         "encounter" => [
        //             "reference" => "Encounter/{{Encounter_id}}"
        //         ],
        //         "effectiveDateTime" => "2024-04-24T03:15:35+00:00",
        //         "issued" => "2024-04-24T03:15:35+00:00",
        //     ],
        //     'request' => ['method' => 'POST', 'url' => 'Specimen']
        // ];
    }

    public function setDiagnosicReport($idEncounter, $observationLabId, $specimenLabId, $serviceRequestLabId, $dataLab, $dokter)
    {
        if ($idEncounter == null) throw new \Exception("Please insert encounter before set observation");
        $uuid = Uuid::uuid4()->toString();

        $id_kategori =  $dataLab->PemeriksaanLab->kategori_loinc_id;

        $coding = [];
        if ($id_kategori ==  1) { //jika mikrobiologi
            $loinc = DB::table('loinc_lab_mikrobiologi_klinik')->where('Code', $dataLab->PemeriksaanLab->Code)->first();
            $coding = [
                [
                    "system" => $loinc->Code_System,
                    "code" => $loinc->Code,
                    "display" => $loinc->Display
                ]
            ];
        } else if ($id_kategori == 2) { //jika kimia klinik
            $loinc = DB::table('loinc_lab_patologi_anatomi')->where('Kode', $dataLab->PemeriksaanLab->Code)->first();
            $coding = [
                [
                    "system" => $loinc->Code_System,
                    "code" => $loinc->Kode,
                    "display" => $loinc->Nama_Pemeriksaan
                ]
            ];
        } else if ($id_kategori == 3) { //jika kimia klinik
            $loinc = DB::table('loinc_lab_patologi_klinik')->where('code', $dataLab->PemeriksaanLab->Code)->first();
            $coding = [
                [
                    "system" => $loinc->code_system,
                    "code" => $loinc->code,
                    "display" => $loinc->display
                ]
            ];
        } else {
            return; // Jika kategori tidak dikenali, tidak melakukan apa-apa
        }

        $pemeriksaanLab = $dataLab->PemeriksaanLab;
        $this->bundleEntry['title_payload'][] = 'diagnostic report';
        $this->bundleEntry['resource'][] = [
            'fullUrl' => "urn:uuid:" . $uuid,
            'resource' =>  [
                "resourceType" => "DiagnosticReport",
                "identifier" => [
                    "system" => "http://sys-ids.kemkes.go.id/diagnostic/" . $this->organizationID . "/lab",
                    "use" => "official",
                    "value" => (string) $dataLab->id
                ],
                "status" => "final",
                "category" => [
                    [
                        "coding" => [
                            [
                                "system" => $pemeriksaanLab->DiagnosticReportCategory->system,
                                "code" => $pemeriksaanLab->DiagnosticReportCategory->code,
                                "display" => $pemeriksaanLab->DiagnosticReportCategory->display
                            ]
                        ]
                    ]
                ],
                "code" => [
                    "coding" => $coding
                ],
                "subject" => [
                    "reference" => $this->patientReference,
                    "display" => $this->patientDisplay
                ],
                "encounter" => [
                    "reference" => $idEncounter,
                    "display" => "Permintaan Pemeriksaan Lab " . $this->patientDisplay . " pada tanggal " . $dataLab->created_at
                ],
                "issued" => $this->formattedDate($dataLab->jam_selesai),
                "performer" => [
                    [
                        "reference" => "Practitioner/" . $dokter->id_dokter_satusehat,
                        "display" => $dokter->nama_lengkap
                    ],
                    [
                        'reference' => 'Organization/' . $this->organizationID
                    ]
                ],
                "result" => [
                    [
                        "reference" => "Observation/" . $observationLabId
                    ]
                ],
                "specimen" => [
                    [
                        "reference" => "Specimen/" . $specimenLabId
                    ]
                ],
                "basedOn" => [
                    [
                        "reference" => "ServiceRequest/" . $serviceRequestLabId
                    ]
                ],
                "conclusionCode" => [
                    [
                        "coding" => [
                            [
                                "system" => $dataLab->DiagnosticReportConclusion->system,
                                "code" => $dataLab->DiagnosticReportConclusion->code,
                                "display" => $dataLab->DiagnosticReportConclusion->display
                            ]
                        ]
                    ]
                ],
            ],
            'request' => ['method' => 'POST', 'url' => 'DiagnosticReport']
        ];

        return $uuid;
    }

    public function setProcedure($idEncounter, $dokter, $tindakan)
    {
        $uuid = Uuid::uuid4()->toString();
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

        $this->bundleEntry['title_payload'][] = 'tindakan';
        $this->bundleEntry['resource'][] = [
            "fullUrl" => "urn:uuid:" . $uuid,
            "resource" => [
                "resourceType" => "Procedure",
                "status" => "completed",
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://hl7.org/fhir/sid/icd-9-cm",
                            "code" => $tindakan->icd9->kode,
                            "display" => $tindakan->icd9->nama,
                        ]
                    ]
                ],
                "subject" => [
                    "reference" => $this->patientReference,
                    "display" => $this->patientDisplay,
                ],
                "encounter" => [
                    "reference" => $idEncounter,
                    "display" => "Tindakan " . $tindakan->icd9->display . " Pada " . $this->patientDisplay,
                ],
                "performer" => [
                    [
                        "actor" => [
                            'reference' => 'Practitioner/' . $dokter->id_dokter_satusehat,
                            'display'   => $dokter->nama_lengkap,
                        ]
                    ]
                ],
            ],
            "request" => ["method" => "POST", "url" => "Procedure"]
        ];
    }

    // public function setCondition($idEncounter, $penyakit, $tanggal)
    // {
    //     $uuid = Uuid::uuid4()->toString();
    //     if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

    //     $this->bundleEntry['title_payload'][] = 'tindakan';
    //     $this->bundleEntry['resource'][] = [
    //         "fullUrl" => "urn:uuid:" . $uuid,
    //         "resource" => [
    //             'resourceType' => 'Condition',
    //             'clinicalStatus' => [
    //                 'coding' => [
    //                     [
    //                         "system"  => "http://terminology.hl7.org/CodeSystem/condition-clinical",
    //                         "code"    => "active",
    //                         "display" => "Active"
    //                     ]
    //                 ]
    //             ],
    //             'category' => [
    //                 [
    //                     'coding' => [
    //                         [
    //                             "system"  => "http://terminology.hl7.org/CodeSystem/condition-category",
    //                             "code"    => "problem-list-item",
    //                             "display" => "Problem List Item"
    //                         ]
    //                     ]
    //                 ]
    //             ],
    //             'code' =>  [
    //                 'coding' => [
    //                     [
    //                         "system"  => "http://hl7.org/fhir/sid/icd-10",
    //                         "code"    => $penyakit->kode,
    //                         "display" => $penyakit->nama
    //                     ]
    //                 ]
    //             ],
    //             'subject' => [
    //                 "reference" => $this->patientReference,
    //                 "display"   => $this->patientDisplay
    //             ],
    //             'encounter' => [
    //                 "reference" => $idEncounter,
    //                 "display"   => "Kunjungan " . $this->patientDisplay . " tanggal " . $tanggal
    //             ]
    //         ],
    //         "request" => ["method" => "POST", "url" => "Condition"]
    //     ];

    //     foreach ($this->bundleEntry as $key => $value) {
    //         if ($value['resource']['resourceType'] == 'Encounter') {
    //             if (!isset($value['resource']['diagnosis'])) {
    //                 $this->bundleEntry[$key]['resource']['diagnosis'] = [];
    //             }

    //             $this->bundleEntry[$key]['resource']['diagnosis'][] = [
    //                 "condition" => [
    //                     "reference" => "urn:uuid:" . $uuid,
    //                     "display" => $penyakit->nama
    //                 ],
    //                 "use" => [
    //                     "coding" => [
    //                         [
    //                             "system" => "http://terminology.hl7.org/CodeSystem/diagnosis-role",
    //                             "code" => "DD",
    //                             "display" => "Discharge diagnosis"
    //                         ]
    //                     ]
    //                 ],
    //                 "rank" => 1
    //             ];
    //             break;
    //         }
    //     }
    // }

    public function setCondition($idEncounter, $penyakit, $tanggal)
    {
        $uuid = Uuid::uuid4()->toString();
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

        $this->bundleEntry['title_payload'][] = 'set condition' . $penyakit->nama;
        $this->bundleEntry['resource'][] = [
            "fullUrl" => "urn:uuid:" . $uuid,
            "resource" => [
                'resourceType' => 'Condition',
                'clinicalStatus' => [
                    'coding' => [
                        [
                            "system"  => "http://terminology.hl7.org/CodeSystem/condition-clinical",
                            "code"    => "active",
                            "display" => "Active"
                        ]
                    ]
                ],
                'category' => [
                    [
                        'coding' => [
                            [
                                "system"  => "http://terminology.hl7.org/CodeSystem/condition-category",
                                "code"    => "problem-list-item",
                                "display" => "Problem List Item"
                            ]
                        ]
                    ]
                ],
                'code' =>  [
                    'coding' => [
                        [
                            "system"  => "http://hl7.org/fhir/sid/icd-10",
                            "code"    => $penyakit->kode,
                            "display" => $penyakit->nama
                        ]
                    ]
                ],
                'subject' => [
                    "reference" => $this->patientReference,
                    "display"   => $this->patientDisplay
                ],
                'encounter' => [
                    "reference" => $idEncounter,
                    "display"   => "Kunjungan " . $this->patientDisplay . " tanggal " . $tanggal
                ]
            ],
            "request" => ["method" => "POST", "url" => "Condition"]
        ];


        foreach ($this->bundleEntry['resource'] as $key => $value) {
            if ($value['resource']['resourceType'] == 'Encounter') {
                if (!isset($value['resource']['diagnosis'])) {
                    $this->bundleEntry['resource'][$key]['resource']['diagnosis'] = [];
                }

                $this->bundleEntry['resource'][$key]['resource']['diagnosis'][] = [
                    "condition" => [
                        "reference" => "urn:uuid:" . $uuid,
                        "display" => $penyakit->nama
                    ],
                    "use" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/diagnosis-role",
                                "code" => "DD",
                                "display" => "Discharge diagnosis"
                            ]
                        ]
                    ],
                    "rank" => 1
                ];
                break;
            }
        }
    }

    // public function setMedicationPrescription($idEncounter, $dokter, $resepObat)
    // {
    //     $uuidMedication = Uuid::uuid4();
    //     $uuidMedicationService = Uuid::uuid4();
    //     if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

    //     $this->bundleEntry[] = [
    //         "fullUrl" => "urn:uuid:" . $uuidMedication,
    //         "resource" => [
    //             "resourceType" => "Medication",
    //             "meta" => [
    //                 "profile" => [
    //                     "https://fhir.kemkes.go.id/r4/StructureDefinition/Medication"
    //                 ]
    //             ],
    //             "identifier" => [
    //                 [
    //                     "system" => "http://sys-ids.kemkes.go.id/medication/" . $this->organizationID,
    //                     "use" => "official",
    //                     "value" => (string) $resepObat['resep']->id
    //                 ]
    //             ],
    //             "code" => [
    //                 "coding" => [
    //                     [
    //                         "system" => "http://sys-ids.kemkes.go.id/kfa",
    //                         "code" => $resepObat['obat']['kode_kfa'],
    //                         "display" => $resepObat['obat']['nama_kfa'],
    //                     ]
    //                 ]
    //             ],
    //             "status" => "active",
    //             "form" => [
    //                 "coding" => [
    //                     [
    //                         "system" => $resepObat['medication']['codesystem'],
    //                         "code" => $resepObat['medication']['code'],
    //                         "display" => $resepObat['medication']['display'],
    //                     ]
    //                 ]
    //             ],
    //             "extension" => [
    //                 [
    //                     "url" => "https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType",
    //                     "valueCodeableConcept" => [
    //                         "coding" => [
    //                             [
    //                                 "system" => "http://terminology.kemkes.go.id/CodeSystem/medication-type",
    //                                 "code" => "NC",
    //                                 "display" => "Non-compound",
    //                             ]
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ],
    //         "request" => ["method" => "POST", "url" => "Medication"]
    //     ];
    //     $this->bundleEntry[] = [
    //         "fullUrl" => "urn:uuid:" . $uuidMedicationService,
    //         "resource" => [
    //             "resourceType" => "MedicationRequest",
    //             "identifier" => [
    //                 [
    //                     "system" => "http://sys-ids.kemkes.go.id/prescription/" . $this->organizationID,
    //                     "use" => "official",
    //                     "value" => (string) $resepObat['resep']->id,
    //                 ],
    //             ],
    //             "status" => "completed",
    //             "intent" => $resepObat['resep']->intent ?? "order",
    //             "category" => [
    //                 [
    //                     "coding" => [
    //                         [
    //                             "system" => "http://terminology.hl7.org/CodeSystem/medicationrequest-category",
    //                             "code" => "discharge",
    //                             "display" => "Discharge"
    //                         ]
    //                     ]
    //                 ]
    //             ],
    //             "priority" => "routine",
    //             "reportedBoolean" => false,
    //             "medicationReference" => [
    //                 "reference" => "urn:uuid:" . $uuidMedication,
    //                 "display" => $resepObat['obat']['nama_kfa']
    //             ],
    //             "subject" => [
    //                 "reference" => $this->patientReference,
    //                 "display" => $this->patientDisplay,
    //             ],
    //             "encounter" => [
    //                 "reference" => $idEncounter,
    //             ],
    //             // FIX ME
    //             "authoredOn" => $this->formattedDate($resepObat['resep']->created),
    //             "requester" => [
    //                 "reference" => "Practitioner/" . $dokter->id_dokter_satusehat,
    //                 "display" => $dokter->nama_lengkap,
    //             ],
    //             "dosageInstruction" => [
    //                 [
    //                     "additionalInstruction" => [
    //                         [
    //                             "text" => $resepObat['resep']->catatan,
    //                         ]
    //                     ],
    //                     "patientInstruction" => $resepObat['resep']->catatan,
    //                     "timing" => [
    //                         "repeat" => [
    //                             "frequency" => $resepObat['resep']->waktu,
    //                             "period" => $resepObat['resep']->hari,
    //                             "periodUnit" => $resepObat['resep']->signa_period,
    //                         ]
    //                     ],
    //                     "route" => [
    //                         "coding" => [
    //                             [
    //                                 "system" => $resepObat['dosis']['codesystem'],
    //                                 "code" => $resepObat['route']['code'],
    //                                 "display" => $resepObat['route']['display'],
    //                             ]
    //                         ]
    //                     ],
    //                     "doseAndRate" => [
    //                         [
    //                             "type" => [
    //                                 "coding" => [
    //                                     [
    //                                         "system" => "http://terminology.hl7.org/CodeSystem/dose-rate-type",
    //                                         "code" => "ordered",
    //                                         "display" => "Ordered"
    //                                     ]
    //                                 ]
    //                             ],
    //                             "doseQuantity" => [
    //                                 "value" => $resepObat['resep']->waktu,
    //                                 "unit" => $resepObat['dosis']['nama'],
    //                                 "system" => $resepObat['dosis']['codesystem'],
    //                                 "code" => $resepObat['dosis']['code'],
    //                             ]
    //                         ]
    //                     ]
    //                 ]
    //             ],
    //             "dispenseRequest" => [
    //                 "performer" => [
    //                     'reference' => 'Organization/' . $this->organizationID
    //                 ],
    //                 "quantity" => [
    //                     "value" => $resepObat['resep']->total,
    //                     "unit" => $resepObat['dosis']['nama'],
    //                     "system" => $resepObat['dosis']['codesystem'],
    //                     "code" => $resepObat['dosis']['code'],
    //                 ],
    //             ]
    //         ],
    //         "request" => [
    //             "method" => "POST",
    //             "url" => "MedicationRequest"
    //         ]
    //     ];
    // }

    // public function setMedicationPrescriptionMixed($idEncounter, $dokter, $data)
    // {

    //     $obat = $data['obat'];
    //     $racik = $data['racik'];
    //     $medication = $data['medication'];
    //     $route = $data['route'];
    //     $satuan = $data['dosis'];

    //     $uuidMedication = Uuid::uuid4();
    //     $uuidMedicationService = Uuid::uuid4();
    //     if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

    //     $ingridients = [];
    //     $ingridientsNames = [];
    //     $medicationRequestIdentifier = [
    //         [
    //             "system" => "http://sys-ids.kemkes.go.id/prescription/" . $this->organizationID,
    //             "use" => "official",
    //             "value" => (string) $racik->id,
    //         ]
    //     ];
    //     foreach ($racik->obat as $racikObat) {
    //         $dataObatRacik = $this->getDataObat($racikObat->id_obat);
    //         $obatDetail =  count($dataObatRacik['obat']) > 0;
    //         $medicationDetail  = count($dataObatRacik['medication']) > 0;
    //         $satuanDetail = count($dataObatRacik['satuan']) > 0;

    //         $ingridients[] = [
    //             "itemCodeableConcept" => [
    //                 "coding" => [
    //                     [
    //                         "system" => "http://sys-ids.kemkes.go.id/kfa",
    //                         "code" => $obatDetail == true ? $dataObatRacik['obat']['kode_kfa'] : '',
    //                         "display" => $obatDetail == true ? $dataObatRacik['obat']['nama_kfa'] : '',
    //                     ]
    //                 ]
    //             ],
    //             "isActive" => true,
    //             "strength" => [
    //                 "numerator" => [
    //                     "value" => $racikObat->dosis,
    //                     "system" => $satuanDetail == true ? $dataObatRacik['satuan']['codesystem'] : '',
    //                     "code" => $satuanDetail == true ? $dataObatRacik['satuan']['code'] : '',
    //                 ],
    //                 "denominator" => [
    //                     "value" => $racik->bungkus,
    //                     "system" => $medicationDetail == true ? $dataObatRacik['medication']['codesystem'] : '',
    //                     "code" => $medicationDetail == true ? $dataObatRacik['medication']['code'] : '',
    //                 ]
    //             ]
    //         ];

    //         $medicationRequestIdentifier[] = [
    //             "system" => "http://sys-ids.kemkes.go.id/prescription-item/" . $this->organizationID,
    //             "use" => "official",
    //             "value" => (string) $racikObat->id,
    //         ];
    //         $ingridientsNames[] = $dataObatRacik['obat']['nama_kfa'];
    //     }

    //     $this->bundleEntry[] = [
    //         "fullUrl" => "urn:uuid:" . $uuidMedication,
    //         "resource" => [
    //             "resourceType" => "Medication",
    //             "meta" => [
    //                 "profile" => [
    //                     "https://fhir.kemkes.go.id/r4/StructureDefinition/Medication"
    //                 ]
    //             ],
    //             "identifier" => [
    //                 [
    //                     "system" => "http://sys-ids.kemkes.go.id/medication/" . $this->organizationID,
    //                     "use" => "official",
    //                     "value" => (string) $racik->id
    //                 ]
    //             ],
    //             "status" => "active",
    //             "form" => [
    //                 "coding" => [
    //                     [
    //                         "system" => $medication['codesystem'],
    //                         "code" => $medication['code'],
    //                         "display" => $medication['display'],
    //                     ]
    //                 ]
    //             ],
    //             "ingredient" => $ingridients,
    //             "extension" => [
    //                 [
    //                     "url" => "https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType",
    //                     "valueCodeableConcept" => [
    //                         "coding" => [
    //                             [
    //                                 "system" => "http://terminology.kemkes.go.id/CodeSystem/medication-type",
    //                                 "code" => "SD",
    //                                 "display" => "Gives of such doses",
    //                             ]
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ],
    //         "request" => ["method" => "POST", "url" => "Medication"]
    //     ];

    //     $this->bundleEntry[] = [
    //         "fullUrl" => "urn:uuid:" . $uuidMedicationService,
    //         "resource" => [
    //             "resourceType" => "MedicationRequest",
    //             "identifier" => $medicationRequestIdentifier,
    //             "status" => "completed",
    //             "intent" => $racik->intent ?? "order",
    //             "category" => [
    //                 [
    //                     "coding" => [
    //                         [
    //                             "system" => "http://terminology.hl7.org/CodeSystem/medicationrequest-category",
    //                             "code" => "discharge",
    //                             "display" => "Discharge"
    //                         ]
    //                     ]
    //                 ]
    //             ],
    //             "priority" => "routine",
    //             "reportedBoolean" => false,
    //             "medicationReference" => [
    //                 "reference" => "urn:uuid:" . $uuidMedication,
    //                 "display" => implode(" / ", $ingridientsNames),
    //             ],
    //             "subject" => [
    //                 "reference" => $this->patientReference,
    //                 "display" => $this->patientDisplay,
    //             ],
    //             "encounter" => [
    //                 "reference" => $idEncounter,
    //             ],
    //             "authoredOn" => $this->formattedDate($racik->created),
    //             "requester" => [
    //                 "reference" => "Practitioner/" . $dokter->id_dokter_satusehat,
    //                 "display" => $dokter->nama_lengkap,
    //             ],
    //             "dosageInstruction" => [
    //                 [
    //                     "additionalInstruction" => [
    //                         [
    //                             "text" => $racik->catatan,
    //                         ]
    //                     ],
    //                     "patientInstruction" => $racik->catatan,
    //                     "timing" => [
    //                         "repeat" => [
    //                             "frequency" => $racik->waktu,
    //                             "period" => 1,
    //                             "periodUnit" => $racik->signa_period,
    //                         ]
    //                     ],
    //                     "route" => [
    //                         "coding" => [
    //                             [
    //                                 "system" => $route['codesystem'],
    //                                 "code" => $route['code'],
    //                                 "display" => $route['display'],
    //                             ]
    //                         ]
    //                     ],
    //                     "doseAndRate" => [
    //                         [
    //                             "doseQuantity" => [
    //                                 "value" => $racik->waktu,
    //                                 "unit" => $satuan['nama'],
    //                                 "system" => $satuan['codesystem'],
    //                                 "code" => $satuan['code'],
    //                             ]
    //                         ]
    //                     ]
    //                 ]
    //             ],
    //             "dispenseRequest" => [
    //                 "performer" => [
    //                     'reference' => 'Organization/' . $this->organizationID
    //                 ],
    //                 "quantity" => [
    //                     "value" => $racik->bungkus,
    //                     "unit" => $satuan['nama'],
    //                     "system" => $satuan['codesystem'],
    //                     "code" => $satuan['code'],
    //                 ],
    //             ]
    //         ],
    //         "request" => [
    //             "method" => "POST",
    //             "url" => "MedicationRequest"
    //         ]
    //     ];
    // }

    public function setMedicationPrescription($idEncounter, $dokter, $resepObat)
    {
        $uuidMedication = Uuid::uuid4();
        $this->medicationUUID = $uuidMedication;
        $uuidMedicationService = Uuid::uuid4();
        $uuidMedicationDispense = Uuid::uuid4();

        if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");
        $dataIngredients = Satusehat_kfa::where('kode_kfa_pa', $resepObat->obat->kode_kfa)->get();
        $ingredient = [];

        $this->bundleEntry['title_payload'][] = 'medication';
        $this->bundleEntry['resource'][] = [
            "fullUrl" => "urn:uuid:" . $uuidMedication,
            "resource" => [
                "resourceType" => "Medication",
                "meta" => [
                    "profile" => [
                        "https://fhir.kemkes.go.id/r4/StructureDefinition/Medication"
                    ]
                ],
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/medication/" . $this->organizationID,
                        "use" => "official",
                        "value" => (string) $resepObat->id . ''
                    ]
                ],
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/kfa",
                            "code" => $resepObat->obat->kode_kfa,
                            "display" => $resepObat->obat->satusehat_kfa->display_name,
                        ]
                    ]
                ],
                "status" => "active",
                "form" => [
                    "coding" => [
                        [
                            "system" => $resepObat->obat->satusehat_medication_form->system,
                            "code" => $resepObat->obat->satusehat_medication_form->code,
                            "display" => $resepObat->obat->satusehat_medication_form->display,
                        ]
                    ]
                ],

                "ingredient" => [
                    [
                        "itemCodeableConcept" => [
                            "coding" => [
                                [
                                    "system" => "http://sys-ids.kemkes.go.id/kfa",
                                    "code" => $resepObat->obat->satusehat_kfa->zat_aktif_kode_kfa_pa,
                                    "display" => $resepObat->obat->satusehat_kfa->product_template_display_name
                                ]
                            ]
                        ],
                        "isActive" => true,
                        "strength" => [
                            "numerator" => [
                                "value" => (float)$resepObat->obat->satusehat_kfa->numerator,
                                "system" => $resepObat->obat->satusehat_kfa->CodeSystem,
                                "code" => $resepObat->obat->satusehat_kfa->satuan
                            ],
                            "denominator" => [
                                "value" => (float)$resepObat->obat->satusehat_kfa->Denominator,
                                "system" => $resepObat->obat->satusehat_kfa->CodeSystem_disesuaikan,
                                "code" => $resepObat->obat->satusehat_kfa->satuan_disesuaikan
                            ]
                        ]
                    ]
                ],

                "extension" => [
                    [
                        "url" => "https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType",
                        "valueCodeableConcept" => [
                            "coding" => [
                                [
                                    "system" => "http://terminology.kemkes.go.id/CodeSystem/medication-type",
                                    "code" => "NC",
                                    "display" => "Non-compound",
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "request" => ["method" => "POST", "url" => "Medication"]
        ];

        $this->bundleEntry['title_payload'][] = 'medication request';
        $this->bundleEntry['resource'][] = [
            "fullUrl" => "urn:uuid:" . $uuidMedicationService,
            "resource" => [
                "resourceType" => "MedicationRequest",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/prescription/" . $this->organizationID,
                        "use" => "official",
                        "value" => (string) $resepObat->id . '',
                    ],
                ],
                "status" => "completed",
                "intent" => $resepObat->intent ?? "order",
                "category" => [
                    [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/medicationrequest-category",
                                "code" => "outpatient",
                                "display" => "Outpatient"
                            ]
                        ]
                    ]
                ],
                "priority" => "routine",
                "reportedBoolean" => false,
                "medicationReference" => [
                    "reference" => "urn:uuid:" . $uuidMedication,
                    "display" => $resepObat->obat->satusehat_kfa->display_name
                ],
                "subject" => [
                    "reference" => $this->patientReference,
                    "display" => $this->patientDisplay,
                ],
                "encounter" => [
                    "reference" => $idEncounter,
                ],
                // FIX ME
                "authoredOn" => $this->formattedDate($resepObat->created),
                "requester" => [
                    "reference" => "Practitioner/" . $dokter->id_dokter_satusehat,
                    "display" => $dokter->nama_lengkap,
                ],
                "dosageInstruction" => [
                    [
                        "additionalInstruction" => [
                            [
                                "text" => $resepObat->catatan,
                            ]
                        ],
                        "patientInstruction" => $resepObat->catatan,
                        "timing" => [
                            "repeat" => [
                                "frequency" => $resepObat->signa1,
                                "period" => $resepObat->signa2,
                                "periodUnit" => $resepObat->signa_period,
                            ]
                        ],
                        "route" => [
                            "coding" => [
                                [
                                    "system" => $resepObat->route->codesystem,
                                    "code" => $resepObat->route->code,
                                    "display" => $resepObat->route->display,
                                ]
                            ]
                        ],
                        "doseAndRate" => [
                            [
                                "type" => [
                                    "coding" => [
                                        [
                                            "system" => "http://terminology.hl7.org/CodeSystem/dose-rate-type",
                                            "code" => "ordered",
                                            "display" => "Ordered"
                                        ]
                                    ]
                                ],
                                "doseQuantity" => [
                                    "value" => $resepObat->signa1,
                                    "unit" => $resepObat->obat->satusehat_kfa->satuan_disesuaikan,
                                    "system" => $resepObat->obat->satusehat_kfa->CodeSystem_disesuaikan,
                                    "code" => $resepObat->obat->satusehat_kfa->satuan_disesuaikan
                                ]
                            ]
                        ]
                    ]
                ],
                "dispenseRequest" => [
                    "quantity" => [
                        "value" => $resepObat->total,
                        "unit" => $resepObat->obat->satusehat_kfa->satuan_disesuaikan,
                        "system" => $resepObat->obat->satusehat_kfa->CodeSystem_disesuaikan,
                        "code" => $resepObat->obat->satusehat_kfa->satuan_disesuaikan
                    ],
                    "performer" => [
                        'reference' => 'Organization/' . $this->organizationID
                    ]
                ]

            ],
            "request" => [
                "method" => "POST",
                "url" => "MedicationRequest"
            ]
        ];

        $this->bundleEntry['title_payload'][] = 'medicaton dispense resep';
        $this->bundleEntry['resource'][] = [
            "fullUrl" => "urn:uuid:" . $uuidMedicationDispense,
            "resource" => [
                "resourceType" => "MedicationDispense",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/prescription/" . $this->organizationID,
                        "use" => "official",
                        "value" => (string) $resepObat->id . ''
                    ],
                    [
                        "system" => "http://sys-ids.kemkes.go.id/prescription-item/" . $this->organizationID,
                        "use" => "official",
                        "value" => (string) $resepObat->id . '' . '-1'
                    ],
                ],
                "status" => "completed",
                "category" => [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/fhir/CodeSystem/medicationdispense-category",
                            "code" => "outpatient",
                            "display" => "Outpatient"
                        ]
                    ]
                ],
                "medicationReference" => [
                    "reference" => "Medication/" . $uuidMedication,
                    "display" => $resepObat->obat->satusehat_kfa->display_name,
                ],
                "subject" => [
                    "reference" => $this->patientReference,
                    "display" => $this->patientDisplay
                ],
                "context" => [
                    "reference" => $idEncounter
                ],
                "performer" => [
                    [
                        "actor" => [
                            "reference" => "Practitioner/" . $dokter->id_dokter_satusehat,
                            "display" => $dokter->nama_lengkap,
                        ]
                    ]
                ],
                "location" => $this->locationRoom,
                "authorizingPrescription" => [
                    [
                        "reference" => "MedicationRequest/" . $uuidMedicationService
                    ]
                ],
                "quantity" => [
                    "value" => $resepObat->total,
                    "unit" => $resepObat->obat->satusehat_kfa->satuan_disesuaikan,
                    "system" => $resepObat->obat->satusehat_kfa->CodeSystem_disesuaikan,
                    "code" => $resepObat->obat->satusehat_kfa->satuan_disesuaikan
                ],
                "daysSupply" => [
                    "value" => 30,
                    "unit" => "Day",
                    "system" => "http://unitsofmeasure.org",
                    "code" => "d"
                ],
                "whenPrepared" => $this->formattedDate($resepObat->created_at),
                "whenHandedOver" => $this->formattedDate($resepObat->created_at),
                "dosageInstruction" => [
                    [
                        "sequence" => 1,
                        "text" => $resepObat->catatan,
                        "timing" => [
                            "repeat" => [
                                "frequency" => $resepObat->signa1,
                                "period" => $resepObat->signa2,
                                "periodUnit" => $resepObat->signa_period,
                            ]
                        ],
                        "doseAndRate" => [
                            [
                                "type" => [
                                    "coding" => [
                                        [
                                            "system" => "http://terminology.hl7.org/CodeSystem/dose-rate-type",
                                            "code" => "ordered",
                                            "display" => "Ordered"
                                        ]
                                    ]
                                ],
                                "doseQuantity" => [
                                    "value" => $resepObat->signa1,
                                    "unit" => $resepObat->obat->satusehat_kfa->satuan,
                                    "system" => $resepObat->obat->satusehat_kfa->CodeSystem,
                                    "code" => $resepObat->obat->satusehat_kfa->satuan
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "request" => [
                "method" => "POST",
                "url" => "MedicationDispense"
            ]
        ];
    }

    public function setMedicationPrescriptionMixed($idEncounter, $dokter, $racik)
    {
        $uuidMedication = Uuid::uuid4();
        $uuidMedicationService = Uuid::uuid4();
        $uuidMedicationDispense = Uuid::uuid4();
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

        $ingridients = [];
        $ingridientsNames = [];
        $medicationRequestIdentifier = [
            [
                "system" => "http://sys-ids.kemkes.go.id/prescription/" . $this->organizationID,
                "use" => "official",
                "value" => (string) $racik->id . '',
            ]
        ];

        foreach ($racik->obat as $racikObat) {
            if ($racikObat->obat->satusehat_kfa) {
                $ingridients[] = [
                    "itemCodeableConcept" => [
                        "coding" => [
                            [
                                "system" => "http://sys-ids.kemkes.go.id/kfa",
                                "code" => $racikObat->obat->satusehat_kfa->kode_kfa_pa,
                                "display" => $racikObat->obat->satusehat_kfa->display_name,
                            ]
                        ]
                    ],
                    "isActive" => true,
                    "strength" => [
                        "numerator" => [
                            "value" => (float)$racikObat->obat->satusehat_kfa->numerator,
                            "system" => $racikObat->obat->satusehat_kfa->CodeSystem,
                            "code" => $racikObat->obat->satusehat_kfa->satuan,
                        ],
                        "denominator" => [
                            "value" => (float)$racikObat->obat->satusehat_kfa->Denominator,
                            "system" => $racikObat->obat->satusehat_kfa->CodeSystem_disesuaikan,
                            "code" => $racikObat->obat->satusehat_kfa->satuan_disesuaikan,
                        ]
                    ]
                ];
                $medicationRequestIdentifier[] = [
                    "system" => "http://sys-ids.kemkes.go.id/prescription-item/" . $this->organizationID,
                    "use" => "official",
                    "value" => (string) $racikObat->id,
                ];
                $ingridientsNames[] = $racikObat->obat->satusehat_kfa->display_name;
            }
        }

        if (count($ingridients) != 0) {
            $this->bundleEntry['title_payload'][] = 'medication_mixed';
            $this->bundleEntry['resource'][] = [
                "fullUrl" => "urn:uuid:" . $uuidMedication,
                "resource" => [
                    "resourceType" => "Medication",
                    "meta" => [
                        "profile" => [
                            "https://fhir.kemkes.go.id/r4/StructureDefinition/Medication"
                        ]
                    ],
                    "identifier" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/medication/" . $this->organizationID,
                            "use" => "official",
                            "value" => (string) $racik->id . ''
                        ]
                    ],
                    "status" => "active",
                    "form" => [
                        "coding" => [
                            [
                                "system" => $racik->satusehat_medication_form->system,
                                "code" => $racik->satusehat_medication_form->code,
                                "display" => $racik->satusehat_medication_form->display,
                            ]
                        ]
                    ],
                    "ingredient" => $ingridients,
                    "extension" => [
                        [
                            "url" => "https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType",
                            "valueCodeableConcept" => [
                                "coding" => [
                                    [
                                        "system" => "http://terminology.kemkes.go.id/CodeSystem/medication-type",
                                        "code" => "SD",
                                        "display" => "Gives of such doses",
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "request" => ["method" => "POST", "url" => "Medication"]
            ];


            $this->bundleEntry['title_payload'][] = 'medication request mixed';
            $this->bundleEntry['resource'][] = [
                "fullUrl" => "urn:uuid:" . $uuidMedicationService,
                "resource" => [
                    "resourceType" => "MedicationRequest",
                    "identifier" => $medicationRequestIdentifier,
                    "status" => "completed",
                    "intent" => $racik->intent ?? "order",
                    "category" => [
                        [
                            "coding" => [
                                [
                                    "system" => "http://terminology.hl7.org/CodeSystem/medicationrequest-category",
                                    "code" => "discharge",
                                    "display" => "Discharge"
                                ]
                            ]
                        ]
                    ],
                    "priority" => "routine",
                    "reportedBoolean" => false,
                    "medicationReference" => [
                        "reference" => "urn:uuid:" . $this->medicationUUID,
                        "display" => implode(" / ", $ingridientsNames),
                    ],
                    "subject" => [
                        "reference" => $this->patientReference,
                        "display" => $this->patientDisplay,
                    ],
                    "encounter" => [
                        "reference" => $idEncounter,
                    ],
                    "authoredOn" => $this->formattedDate($racik->created),
                    "requester" => [
                        "reference" => "Practitioner/" . $dokter->id_dokter_satusehat,
                        "display" => $dokter->nama_lengkap,
                    ],
                    "dosageInstruction" => [
                        [
                            "additionalInstruction" => [
                                [
                                    "text" => $racik->catatan,
                                ]
                            ],
                            "patientInstruction" => $racik->catatan,
                            "timing" => [
                                "repeat" => [
                                    "frequency" => $racik->signa1,
                                    "period" => $racik->signa2,
                                    "periodUnit" => $racik->signa_period,
                                ]
                            ],
                            "route" => [
                                "coding" => [
                                    [
                                        "system" => $racik->route->codesystem,
                                        "code" => $racik->route->code,
                                        "display" => $racik->route->display,
                                    ]
                                ]
                            ],
                            "doseAndRate" => [
                                [
                                    "doseQuantity" => [
                                        "value" => (float)$racik->signa1,
                                        "unit" => $racik->bentuk_sediaan->display,
                                        "system" => $racik->bentuk_sediaan->system,
                                        "code" => $racik->bentuk_sediaan->code,
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "dispenseRequest" => [
                        "performer" => [
                            'reference' => 'Organization/' . $this->organizationID
                        ],
                        "quantity" => [
                            "value" => (float)$racik->signa1,
                            "unit" => $racik->bentuk_sediaan->display,
                            "system" => $racik->bentuk_sediaan->system,
                            "code" => $racik->bentuk_sediaan->code,
                        ],
                    ]
                ],
                "request" => [
                    "method" => "POST",
                    "url" => "MedicationRequest"
                ]
            ];

            // dd($racik->satusehat_medication_form);


            $this->bundleEntry['title_payload'][] = 'medicaton dispense racik';
            $this->bundleEntry['resource'][] = [
                "fullUrl" => "urn:uuid:" . $uuidMedicationDispense,
                "resource" => [
                    "resourceType" => "MedicationDispense",
                    "identifier" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/prescription/" . $this->organizationID,
                            "use" => "official",
                            "value" => (string) $racik->id . ''
                        ],
                        [
                            "system" => "http://sys-ids.kemkes.go.id/prescription-item/" . $this->organizationID,
                            "use" => "official",
                            "value" => (string) $racik->id . '' . '-1'
                        ],
                    ],
                    "status" => "completed",
                    "category" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/fhir/CodeSystem/medicationdispense-category",
                                "code" => "outpatient",
                                "display" => "Outpatient"
                            ]
                        ]
                    ],
                    "medicationReference" => [
                        "reference" => "Medication/" . $uuidMedication,
                        "display" => '-',
                    ],
                    "subject" => [
                        "reference" => $this->patientReference,
                        "display" => $this->patientDisplay
                    ],
                    "context" => [
                        "reference" => $idEncounter
                    ],
                    "performer" => [
                        [
                            "actor" => [
                                "reference" => "Practitioner/" . $dokter->id_dokter_satusehat,
                                "display" => $dokter->nama_lengkap,
                            ]
                        ]
                    ],
                    "location" => $this->locationRoom,
                    "authorizingPrescription" => [
                        [
                            "reference" => "MedicationRequest/" . $uuidMedicationService
                        ]
                    ],
                    "quantity" => [
                        "value" => $racik->bungkus,
                        "unit" => $racik->bentuk_sediaan->code,
                        "system" => $racik->bentuk_sediaan->system,
                        "code" => $racik->bentuk_sediaan->code
                    ],
                    "daysSupply" => [
                        "value" => 30,
                        "unit" => "Day",
                        "system" => "http://unitsofmeasure.org",
                        "code" => "d"
                    ],
                    "whenPrepared" => $this->formattedDate($racik->created),
                    "whenHandedOver" => $this->formattedDate($racik->created),
                    "dosageInstruction" => [
                        [
                            "sequence" => 1,
                            "text" => $racik->catatan,
                            "timing" => [
                                "repeat" => [
                                    "frequency" => $racik->signa1,
                                    "period" => $racik->signa2,
                                    "periodUnit" => $racik->signa_period,
                                ]
                            ],
                            "doseAndRate" => [
                                [
                                    "type" => [
                                        "coding" => [
                                            [
                                                "system" => "http://terminology.hl7.org/CodeSystem/dose-rate-type",
                                                "code" => "ordered",
                                                "display" => "Ordered"
                                            ]
                                        ]
                                    ],
                                    "doseQuantity" => [
                                        "value" => $racik->signa1,
                                        "unit" => $racik->bentuk_sediaan->display,
                                        "system" => $racik->bentuk_sediaan->system,
                                        "code" => $racik->bentuk_sediaan->code
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "request" => [
                    "method" => "POST",
                    "url" => "MedicationDispense"
                ]
            ];
        }
    }

    public function getDataObat($id_obat = "")
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

    public function send($idEncounter)
    {
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before sending");

        $body = [
            "resourceType" => "Bundle",
            "type" => "transaction",
            "entry" => $this->bundleEntry['resource']
        ];

        if (empty($this->bundleEntry)) {
            return [
                'ket' => 'yes',
                'result' => "This data already saved",
                'body' => json_encode($body),
                'id_encounter' => $idEncounter,
            ];
        }

        $oAuthClient = new OAuth2Client;
        $access_token = $oAuthClient->token();

        if (!isset($access_token)) {
            throw new \Exception("Access token not provided");
        }

        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ];

        Log::info("body", ['body' => $body]);

        $url = $oAuthClient->base_url;
        $request = new Request('POST', $url, $headers, collect($body));

        try {
            $res = $client->sendAsync($request)->wait();
            $statusCode = $res->getStatusCode();
            $response = json_decode($res->getBody()->getContents(), true);
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $response = json_decode($e->getResponse()->getBody()->getContents(), true);
        }

        if ($statusCode == 200) {
            if ($response['entry'][0]['response']['resourceID'] == 'Encounter') {
                $idEncounter = $response['entry'][0]['response']['resourceID'];
            }
            return [
                'ket' => 'yes',
                'result' => $response,
                'body' => json_encode($body),
                'id_encounter' => $idEncounter,
            ];
        } else if ($statusCode == 400) {
            return [
                'key' => 'no',
                'result' => $response,
                'body' => json_encode($body),
                'message' => $response['issue'][0]['details']['text'],
            ];
        } else {
            return [
                'key' => 'no',
                'result' => $response,
                'body' => json_encode($body),
                'message' => 'Server error',
            ];
        }
    }


    public function sendPribadi($idEncounter, $id_users)
    {
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before sending");

        $body = [
            "resourceType" => "Bundle",
            "type" => "transaction",
            "entry" => $this->bundleEntry
        ];

        // Log::info('laravel', ['body' => $body]);
        // echo json_encode($body);
        // die;

        if (empty($this->bundleEntry)) {
            Log::info('encounter', ['idEncounter' => $idEncounter]);
            return [
                'ket' => 'yes',
                'result' => "This data already saved",
                'body' => json_encode($body),
                'id_encounter' => $idEncounter,
            ];
        }

        $oAuthClientPribadi = new OAuth2ClientPribadi;
        $access_token = $oAuthClientPribadi->token($id_users);

        Log::info('access_token', ['access_token' => $access_token]);

        // Log::info('laravel', ['access_token' => $access_token]);


        if (!isset($access_token)) {
            throw new \Exception("Access token not provided");
        }

        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ];

        $url = $oAuthClientPribadi->base_url;
        $request = new Request('POST', $url, $headers, collect($body));

        try {
            $res = $client->sendAsync($request)->wait();
            $statusCode = $res->getStatusCode();
            $response = json_decode($res->getBody()->getContents(), true);
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $response = json_decode($e->getResponse()->getBody()->getContents(), true);
        }

        if ($statusCode == 200) {
            if ($response['entry'][0]['response']['resourceID'] == 'Encounter') {
                $idEncounter = $response['entry'][0]['response']['resourceID'];
            }
            return [
                'ket' => 'yes',
                'result' => $response,
                'body' => json_encode($body),
                'id_encounter' => $idEncounter,
            ];
        } else if ($statusCode == 400) {
            return [
                'key' => 'no',
                'result' => $response,
                'body' => json_encode($body),
                'message' => $response['issue'][0]['details']['text'],
            ];
        } else {
            return [
                'key' => 'no',
                'result' => $response,
                'body' => json_encode($body),
                'message' => 'Server error',
            ];
        }
    }

    private function formattedDate($datetime)
    {
        $timezone = new DateTimeZone('Asia/Jakarta');
        $dateTime = new DateTime($datetime, $timezone);
        $formattedDateTime = $dateTime->format('Y-m-d\TH:i:sP');
        return $formattedDateTime;
    }



    public function locationCreate($body_satusehat, $id_users, $data_ruangan)
    {

        $body = $body_satusehat;

        $oAuthClientPribadi = new OAuth2ClientPribadi;
        $access_token = $oAuthClientPribadi->token($id_users);

        // Log::info('laravel', ['access_token' => $access_token]);


        if (!isset($access_token)) {
            throw new \Exception("Access token not provided");
        }

        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ];



        $url = $oAuthClientPribadi->base_url . '/Location';
        $request = new Request('POST', $url, $headers, collect($body));



        try {
            $res = $client->sendAsync($request)->wait();
            $statusCode = $res->getStatusCode();
            $response = json_decode($res->getBody()->getContents(), true);
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $response = json_decode($e->getResponse()->getBody()->getContents(), true);
        }


        Log::info('laravel', ['statusCode' => $statusCode, 'response' => $response]);

        if ($statusCode == 201 || $statusCode == 200) {
            if (isset($response['id'])) {
                $idRuanganSatuSehat = $response['id'];

                // Simpan data ke kk_ruangan_pemeriksaan
                $inpRuangan = [
                    'nama' => $data_ruangan['nama'] ?? null,
                    'kode_ruangan' => $data_ruangan['kode_ruangan'] ?? null, // Pastikan variabel ini sudah didefinisikan sebelumnya
                    'id_ruangan_satusehat' => $response['id'] ?? null,
                    'location_type' => $data_ruangan['location_type'] ?? null, // Pastikan variabel ini sudah didefinisikan sebelumnya
                    'ruangan' => $data_ruangan['ruangan'] ?? null, // Pastikan variabel ini sudah didefinisikan sebelumnya
                    'id_user' => $data_ruangan['id_user'] ?? null,
                    'id_dokter' => $data_ruangan['id_dokter'] ?? null,
                    'created_at' => date("Y-m-d H:i:s")
                ];

                DB::table('kk_ruangan_pemeriksaan')->insert($inpRuangan);
            }

            return [
                'ket' => 'yes',
                'statusCode' => $statusCode,
                'result' => $response,
                'body' => json_encode($body),
                'id_ruangan_satusehat' => $idRuanganSatuSehat ?? null,
            ];
        } else if ($statusCode == 400) {
            return [
                'key' => 'no',
                'statusCode' => $statusCode,
                'result' => $response,
                'body' => json_encode($body),
                'message' => $response['issue'][0]['details']['text'] ?? 'Bad request',
            ];
        } else {
            return [
                'key' => 'no',
                'statusCode' => $statusCode,
                'result' => $response,
                'body' => json_encode($body),
                'message' => 'Server error',
            ];
        }
    }
}
