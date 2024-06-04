<?php

namespace App\Services\Satusehat;

use App\Models\CatatanPasien;
use App\Models\Perusahaan;
use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use Ramsey\Uuid\Uuid;

class Bundle
{
    public $organizationID, $patientReference, $patientDisplay, $bundleEntry;

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
        $id = Uuid::uuid4()->toString();
        $this->bundleEntry[] = [
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

        $this->bundleEntry[] = [
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

        $this->bundleEntry[] = [
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

    public function setProcedure($idEncounter, $dokter, $tindakan)
    {
        $uuid = Uuid::uuid4()->toString();
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

        $this->bundleEntry[] = [
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

    public function setCondition($idEncounter, $penyakit, $tanggal)
    {
        $uuid = Uuid::uuid4()->toString();
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

        $this->bundleEntry[] = [
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

        foreach ($this->bundleEntry as $key => $value) {
            if ($value['resource']['resourceType'] == 'Encounter') {
                if (!isset($value['resource']['diagnosis'])) {
                    $this->bundleEntry[$key]['resource']['diagnosis'] = [];
                }

                $this->bundleEntry[$key]['resource']['diagnosis'][] = [
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

    public function setMedicationPrescription($idEncounter, $dokter, $resepObat)
    {
        $uuidMedication = Uuid::uuid4();
        $uuidMedicationService = Uuid::uuid4();
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

        $this->bundleEntry[] = [
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
                        "value" => (string) $resepObat['resep']->id
                    ]
                ],
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/kfa",
                            "code" => $resepObat['obat']['kode_kfa'],
                            "display" => $resepObat['obat']['nama_kfa'],
                        ]
                    ]
                ],
                "status" => "active",
                "form" => [
                    "coding" => [
                        [
                            "system" => $resepObat['medication']['codesystem'],
                            "code" => $resepObat['medication']['code'],
                            "display" => $resepObat['medication']['display'],
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
        $this->bundleEntry[] = [
            "fullUrl" => "urn:uuid:" . $uuidMedicationService,
            "resource" => [
                "resourceType" => "MedicationRequest",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/prescription/" . $this->organizationID,
                        "use" => "official",
                        "value" => (string) $resepObat['resep']->id,
                    ],
                ],
                "status" => "completed",
                "intent" => $resepObat['resep']->intent ?? "order",
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
                    "reference" => "urn:uuid:" . $uuidMedication,
                    "display" => $resepObat['obat']['nama_kfa']
                ],
                "subject" => [
                    "reference" => $this->patientReference,
                    "display" => $this->patientDisplay,
                ],
                "encounter" => [
                    "reference" => $idEncounter,
                ],
                // FIX ME
                "authoredOn" => $this->formattedDate($resepObat['resep']->created),
                "requester" => [
                    "reference" => "Practitioner/" . $dokter->id_dokter_satusehat,
                    "display" => $dokter->nama_lengkap,
                ],
                "dosageInstruction" => [
                    [
                        "additionalInstruction" => [
                            [
                                "text" => $resepObat['resep']->catatan,
                            ]
                        ],
                        "patientInstruction" => $resepObat['resep']->catatan,
                        "timing" => [
                            "repeat" => [
                                "frequency" => $resepObat['resep']->waktu,
                                "period" => $resepObat['resep']->hari,
                                "periodUnit" => $resepObat['resep']->signa_period,
                            ]
                        ],
                        "route" => [
                            "coding" => [
                                [
                                    "system" => $resepObat['dosis']['codesystem'],
                                    "code" => $resepObat['route']['code'],
                                    "display" => $resepObat['route']['display'],
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
                                    "value" => $resepObat['resep']->waktu,
                                    "unit" => $resepObat['dosis']['nama'],
                                    "system" => $resepObat['dosis']['codesystem'],
                                    "code" => $resepObat['dosis']['code'],
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
                        "value" => $resepObat['resep']->total,
                        "unit" => $resepObat['dosis']['nama'],
                        "system" => $resepObat['dosis']['codesystem'],
                        "code" => $resepObat['dosis']['code'],
                    ],
                ]
            ],
            "request" => [
                "method" => "POST",
                "url" => "MedicationRequest"
            ]
        ];
    }

    public function setMedicationPrescriptionMixed($idEncounter, $dokter, $data)
    {

        $obat = $data['obat'];
        $racik = $data['racik'];
        $medication = $data['medication'];
        $route = $data['route'];
        $satuan = $data['dosis'];

        $uuidMedication = Uuid::uuid4();
        $uuidMedicationService = Uuid::uuid4();
        if (empty($idEncounter)) throw new \Exception("Please insert encounter before set condition");

        $ingridients = [];
        $ingridientsNames = [];
        $medicationRequestIdentifier = [
            [
                "system" => "http://sys-ids.kemkes.go.id/prescription/" . $this->organizationID,
                "use" => "official",
                "value" => (string) $racik->id,
            ]
        ];
        foreach ($racik->obat as $racikObat) {
            $dataObatRacik = $this->getDataObat($racikObat->id_obat);
            $obatDetail =  count($dataObatRacik['obat']) > 0;
            $medicationDetail  = count($dataObatRacik['medication']) > 0;
            $satuanDetail = count($dataObatRacik['satuan']) > 0;

            $ingridients[] = [
                "itemCodeableConcept" => [
                    "coding" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/kfa",
                            "code" => $obatDetail == true ? $dataObatRacik['obat']['kode_kfa'] : '',
                            "display" => $obatDetail == true ? $dataObatRacik['obat']['nama_kfa'] : '',
                        ]
                    ]
                ],
                "isActive" => true,
                "strength" => [
                    "numerator" => [
                        "value" => $racikObat->dosis,
                        "system" => $satuanDetail == true ? $dataObatRacik['satuan']['codesystem'] : '',
                        "code" => $satuanDetail == true ? $dataObatRacik['satuan']['code'] : '',
                    ],
                    "denominator" => [
                        "value" => $racik->bungkus,
                        "system" => $medicationDetail == true ? $dataObatRacik['medication']['codesystem'] : '',
                        "code" => $medicationDetail == true ? $dataObatRacik['medication']['code'] : '',
                    ]
                ]
            ];

            $medicationRequestIdentifier[] = [
                "system" => "http://sys-ids.kemkes.go.id/prescription-item/" . $this->organizationID,
                "use" => "official",
                "value" => (string) $racikObat->id,
            ];
            $ingridientsNames[] = $dataObatRacik['obat']['nama_kfa'];
        }

        $this->bundleEntry[] = [
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
                        "value" => (string) $racik->id
                    ]
                ],
                "status" => "active",
                "form" => [
                    "coding" => [
                        [
                            "system" => $medication['codesystem'],
                            "code" => $medication['code'],
                            "display" => $medication['display'],
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

        $this->bundleEntry[] = [
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
                    "reference" => "urn:uuid:" . $uuidMedication,
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
                                "frequency" => $racik->waktu,
                                "period" => 1,
                                "periodUnit" => $racik->signa_period,
                            ]
                        ],
                        "route" => [
                            "coding" => [
                                [
                                    "system" => $route['codesystem'],
                                    "code" => $route['code'],
                                    "display" => $route['display'],
                                ]
                            ]
                        ],
                        "doseAndRate" => [
                            [
                                "doseQuantity" => [
                                    "value" => $racik->waktu,
                                    "unit" => $satuan['nama'],
                                    "system" => $satuan['codesystem'],
                                    "code" => $satuan['code'],
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
                        "value" => $racik->bungkus,
                        "unit" => $satuan['nama'],
                        "system" => $satuan['codesystem'],
                        "code" => $satuan['code'],
                    ],
                ]
            ],
            "request" => [
                "method" => "POST",
                "url" => "MedicationRequest"
            ]
        ];
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
            "entry" => $this->bundleEntry
        ];

        echo json_encode($body);
        die;


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

    private function formattedDate($datetime)
    {
        $timezone = new DateTimeZone('Asia/Jakarta');
        $dateTime = new DateTime($datetime, $timezone);
        $formattedDateTime = $dateTime->format('Y-m-d\TH:i:sP');
        return $formattedDateTime;
    }
}
