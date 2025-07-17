<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function GetScheduleSync(Request $request)
    {
        $kategori = $request->input('kategori', 'stok_obat'); // Default to 'default' if not provided
        $data = DB::table('kk_schedule_sync_obat')->where('kategori', $kategori)->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Schedule synchronization successful',
            'data' => $data
        ]);
    }

    public function CreateScheduleSync(Request $request)
    {
        $data = DB::table('kk_schedule_sync_obat')->insert([
            'waktu' => $request->input('waktu'),
            'status' => $request->input('status'),
            'kategori' => $request->input('kategori'),
            'keterangan' => $request->input('keterangan'),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Schedule synchronization created successfully',
            'data' => $data
        ]);
    }

    public function UpdateScheduleSync(Request $request)
    {
        $id =   $request->input('id');
        $data =  $request->input('data');

        DB::table('kk_schedule_sync_obat')->where('id', $id)->update($data);
        return response()->json([
            'status' => 'success',
            'message' => 'Schedule synchronization updated successfully',
        ]);
    }

    public function DeleteScheduleSync(Request $request)
    {
        DB::table('kk_schedule_sync_obat')->where('id', $request->input('id'))->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Schedule synchronization deleted successfully',
        ]);
    }
}
