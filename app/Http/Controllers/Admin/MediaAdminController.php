<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\MediaUploadService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MediaAdminController extends Controller
{
    public function __construct(private readonly MediaUploadService $uploadService) {}

    public function index(): Response
    {
        return Inertia::render('Media/Index');
    }

    public function show(string $id): Response
    {
        $asset = MediaAsset::findOrFail($id);

        return Inertia::render('Media/Index', [
            'activeAssetId' => $asset->id,
        ]);
    }

    public function destroy(string $id): RedirectResponse
    {
        $asset = MediaAsset::findOrFail($id);

        $this->uploadService->delete($asset);

        return redirect()->route('admin.media')->with('success', 'Media asset deleted.');
    }
}
