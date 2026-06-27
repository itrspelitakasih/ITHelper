<?php

namespace App\Http\Controllers\Lis;

use App\Http\Controllers\Controller;
use App\Models\Lis\Kode;
use App\Models\Lis\KodeSimrs;
use App\Models\Lis\Tarif;
use App\Models\Lis\Paket;
use App\Models\Lis\PaketDetail;
use App\Models\Lis\Dokter;
use App\Models\Lis\Ruang;
use App\Models\Lis\Petugas;
use App\Models\Lis\Parameter;
use App\Services\ExternalDatabaseManager;
use Illuminate\Http\Request;
use Throwable;

class LisReferensiController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager)
    {
        $tab = $request->input('tab', 'pemeriksaan');

        $data = [];
        $connectionError = null;

        if ($tab === 'pemeriksaan') {
            $data = Kode::orderBy('grup1')->orderBy('order')->paginate(20)->withQueryString();
        } elseif ($tab === 'mapping') {
            $data = KodeSimrs::orderBy('grup')->paginate(20)->withQueryString();
        } elseif ($tab === 'tarif') {
            $data = Tarif::orderBy('pemeriksaan')->paginate(20)->withQueryString();
        } elseif ($tab === 'paket') {
            $data = Paket::orderBy('nama')->paginate(20)->withQueryString();
        } elseif (in_array($tab, ['dokter', 'ruang', 'petugas'])) {
            try {
                $extConn = $databaseManager->connection();
                if ($tab === 'dokter') {
                    $data = $extConn->table('dokter')
                        ->where('status', '1')
                        ->select(['kd_dokter as id_dokter', 'nm_dokter as nama'])
                        ->orderBy('nm_dokter')
                        ->paginate(20)
                        ->withQueryString();
                } elseif ($tab === 'ruang') {
                    $data = $extConn->table('poliklinik')
                        ->where('status', '1')
                        ->select(['kd_poli as poli_id', 'nm_poli as nama'])
                        ->orderBy('nm_poli')
                        ->paginate(20)
                        ->withQueryString();
                } else { // petugas
                    $data = $extConn->table('petugas')
                        ->where('status', '1')
                        ->where('kd_jbtn', 'J014')
                        ->select(['nip', 'nama'])
                        ->orderBy('nama')
                        ->paginate(20)
                        ->withQueryString();
                }
            } catch (Throwable $e) {
                $connectionError = 'Database eksternal SIMRS belum dapat diakses. Silakan periksa konfigurasi pada menu Pengaturan > Bridging.';
                $data = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            }
        }

        return view('pages.lis.referensi.index', [
            'title' => 'Referensi LIS',
            'tab' => $tab,
            'data' => $data,
            'connectionError' => $connectionError,
        ]);
    }

    public function store(Request $request, $type)
    {
        if ($type === 'pemeriksaan') {
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'lis' => ['required', 'string', 'max:50'],
                'satuan' => ['nullable', 'string', 'max:50'],
                'metoda' => ['nullable', 'string', 'max:100'],
                'grup1' => ['required', 'string', 'max:100'],
                'grup2' => ['nullable', 'string', 'max:100'],
                'grup3' => ['nullable', 'string', 'max:100'],
                'order' => ['nullable', 'integer'],
            ]);
            Kode::create($validated);
        } elseif ($type === 'mapping') {
            $validated = $request->validate([
                'grup' => ['required', 'string', 'max:50'],
                'kode' => ['required', 'string', 'max:50'],
                'lis' => ['required', 'string', 'max:50'],
                'pemeriksaan' => ['nullable', 'string', 'max:100'],
            ]);
            KodeSimrs::create($validated);
        } elseif ($type === 'tarif') {
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'biaya' => ['required', 'numeric'],
            ]);
            Tarif::create($validated);
        } elseif ($type === 'paket') {
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
            ]);
            Paket::create($validated);
        } elseif ($type === 'dokter') {
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'id_dokter' => ['required', 'string', 'max:50'],
            ]);
            Dokter::create(array_merge($validated, ['kode' => 1]));
        } elseif ($type === 'ruang') {
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'poli_id' => ['required', 'string', 'max:50'],
            ]);
            Ruang::create($validated);
        } elseif ($type === 'petugas') {
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
            ]);
            Petugas::create($validated);
        }

        return back()->with('success', 'Data referensi berhasil ditambahkan.');
    }

    public function update(Request $request, $type)
    {
        $id = $request->input('id');
        if ($type === 'pemeriksaan') {
            $model = Kode::findOrFail($id);
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'lis' => ['required', 'string', 'max:50'],
                'satuan' => ['nullable', 'string', 'max:50'],
                'metoda' => ['nullable', 'string', 'max:100'],
                'grup1' => ['required', 'string', 'max:100'],
                'grup2' => ['nullable', 'string', 'max:100'],
                'grup3' => ['nullable', 'string', 'max:100'],
                'order' => ['nullable', 'integer'],
            ]);
            $model->update($validated);
        } elseif ($type === 'mapping') {
            $model = KodeSimrs::findOrFail($id);
            $validated = $request->validate([
                'grup' => ['required', 'string', 'max:50'],
                'kode' => ['required', 'string', 'max:50'],
                'lis' => ['required', 'string', 'max:50'],
                'pemeriksaan' => ['nullable', 'string', 'max:100'],
            ]);
            $model->update($validated);
        } elseif ($type === 'tarif') {
            $model = Tarif::findOrFail($id);
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'biaya' => ['required', 'numeric'],
            ]);
            $model->update($validated);
        } elseif ($type === 'paket') {
            $model = Paket::findOrFail($id);
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
            ]);
            $model->update($validated);
        } elseif ($type === 'dokter') {
            $model = Dokter::findOrFail($id);
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'id_dokter' => ['required', 'string', 'max:50'],
            ]);
            $model->update($validated);
        } elseif ($type === 'ruang') {
            $model = Ruang::findOrFail($id);
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'poli_id' => ['required', 'string', 'max:50'],
            ]);
            $model->update($validated);
        } elseif ($type === 'petugas') {
            $model = Petugas::findOrFail($id);
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:255'],
            ]);
            $model->update($validated);
        }

        return back()->with('success', 'Data referensi berhasil diperbarui.');
    }

    public function destroy(Request $request, $type)
    {
        $id = $request->input('id');
        if ($type === 'pemeriksaan') {
            Kode::destroy($id);
        } elseif ($type === 'mapping') {
            KodeSimrs::destroy($id);
        } elseif ($type === 'tarif') {
            Tarif::destroy($id);
        } elseif ($type === 'paket') {
            Paket::destroy($id);
            PaketDetail::where('id_paket', $id)->delete();
        } elseif ($type === 'dokter') {
            Dokter::destroy($id);
        } elseif ($type === 'ruang') {
            Ruang::destroy($id);
        } elseif ($type === 'petugas') {
            Petugas::destroy($id);
        }

        return back()->with('success', 'Data referensi berhasil dihapus.');
    }

    public function showPaket($id)
    {
        $paket = Paket::findOrFail($id);

        // Get all detail items in this package
        $paketDetail = PaketDetail::where('id_paket', $id)
            ->join('kode', 'paket_detail.id_kode', '=', 'kode.id')
            ->select('paket_detail.id as detail_id', 'kode.*')
            ->get();

        // Get all codes that are not already in this package
        $existingKodeIds = PaketDetail::where('id_paket', $id)->pluck('id_kode')->toArray();
        $availableKode = Kode::whereNotIn('id', $existingKodeIds)
            ->orderBy('nama')
            ->get();

        return view('pages.lis.referensi.paket.show', [
            'title' => 'Detail Paket LIS: ' . $paket->nama,
            'paket' => $paket,
            'detail' => $paketDetail,
            'availableKode' => $availableKode
        ]);
    }

    public function addPaketDetail(Request $request, $id)
    {
        $paket = Paket::findOrFail($id);

        $validated = $request->validate([
            'kode_ids' => ['required', 'string'],
        ]);

        $ids = explode(',', $validated['kode_ids']);
        foreach ($ids as $kodeId) {
            $kodeId = trim($kodeId);
            if (!empty($kodeId)) {
                PaketDetail::firstOrCreate([
                    'id_paket' => $paket->id,
                    'id_kode' => $kodeId
                ]);
            }
        }

        return back()->with('success', 'Parameter berhasil ditambahkan ke dalam paket.');
    }

    public function deletePaketDetail(Request $request, $id)
    {
        $detailId = $request->input('id');
        PaketDetail::where('id_paket', $id)->where('id', $detailId)->delete();

        return back()->with('success', 'Parameter berhasil dihapus dari paket.');
    }
}
