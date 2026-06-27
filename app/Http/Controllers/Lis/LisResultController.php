<?php

namespace App\Http\Controllers\Lis;

use App\Http\Controllers\Controller;
use App\Models\Lis\Result;
use App\Models\Lis\Periksa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LisResultController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $query = DB::connection('lis')->table('v_result');

        if ($search !== '') {
            $query->where('KodePatient', 'like', "%{$search}%")
                  ->orWhere('KodeAlat', 'like', "%{$search}%");
        }

        $rows = $query->orderByDesc('tanggal')->paginate(20)->withQueryString();

        return view('pages.lis.result.index', [
            'title' => 'Hasil Analyzer Raw',
            'rows' => $rows,
            'search' => $search
        ]);
    }

    public function acc(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'value' => ['required', 'string'],
        ]);

        $result = Result::findOrFail($data['id']);
        $result->acc = $data['value'] === 'true' ? '1' : '0';
        $result->save();

        $periksa = Periksa::where('nomor', $result->KodePatient)->first();
        if ($periksa) {
            $periksa->update_by = auth()->user()->name ?? 'System';
            $periksa->update_at = now()->toDateTimeString();
            $periksa->save();
        }

        return response()->json(['status' => 'success']);
    }

    public function gacc(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'string'], // grup1 name
            'value' => ['required', 'string'],
            'nomor' => ['required', 'string'], // KodePatient
        ]);

        $acc = $data['value'] === 'true' ? '1' : '0';

        $detail = DB::connection('lis')
            ->table('result as a')
            ->leftJoin('kode as b', 'a.KodeParamater', '=', 'b.lis')
            ->where('a.KodePatient', 'like', "%{$data['nomor']}%")
            ->where('b.grup1', $data['id'])
            ->select('a.KodeParamater')
            ->get();

        foreach ($detail as $row) {
            Result::where('KodePatient', 'like', "%{$data['nomor']}%")
                ->where('KodeParamater', $row->KodeParamater)
                ->update(['acc' => $acc]);
        }

        $periksa = Periksa::where('nomor', $data['nomor'])->first();
        if ($periksa) {
            $periksa->update_by = auth()->user()->name ?? 'System';
            $periksa->update_at = now()->toDateTimeString();
            $periksa->save();
        }

        return response()->json(['status' => 'success']);
    }

    public function nilai(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'value' => ['nullable', 'string'],
        ]);

        $result = Result::findOrFail($data['id']);
        $result->Nilai = $data['value'];
        $result->save();

        $periksa = Periksa::where('nomor', $result->KodePatient)->first();
        if ($periksa) {
            $periksa->update_by = auth()->user()->name ?? 'System';
            $periksa->update_at = now()->toDateTimeString();
            $periksa->save();
        }

        return response()->json(['status' => 'success']);
    }

    public function nr(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'value' => ['nullable', 'string'],
        ]);

        $result = Result::findOrFail($data['id']);
        $result->NR = $data['value'];
        $result->save();

        return response()->json(['status' => 'success']);
    }

    public function tanda(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'value' => ['nullable', 'string'],
        ]);

        $result = Result::findOrFail($data['id']);
        $result->tanda = $data['value'];
        $result->save();

        return response()->json(['status' => 'success']);
    }

    public function keterangan(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'value' => ['nullable', 'string'],
        ]);

        $result = Result::findOrFail($data['id']);
        $result->keterangan = $data['value'];
        $result->save();

        $periksa = Periksa::where('nomor', $result->KodePatient)->first();
        if ($periksa) {
            $periksa->update_by = auth()->user()->name ?? 'System';
            $periksa->update_at = now()->toDateTimeString();
            $periksa->save();
        }

        return response()->json(['status' => 'success']);
    }

    public function delete(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        if (!empty($data['ids'])) {
            Result::destroy($data['ids']);
        } elseif (!empty($data['id'])) {
            Result::destroy($data['id']);
        }

        return response()->json(['status' => 'success']);
    }

    public function hapus(Request $request)
    {
        $data = $request->validate([
            'no' => ['required', 'string'],
        ]);

        Result::where('KodePatient', 'like', "%{$data['no']}%")->delete();

        return response()->json(['status' => 'success']);
    }
}
