<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        return \App\Models\Announcement::where('is_active', true)->latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'boolean',
        ]);

        return \App\Models\Announcement::create($validated);
    }

    public function update(Request $request, $id)
    {
        $announcement = \App\Models\Announcement::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'string|max:255',
            'content' => 'string',
            'is_active' => 'boolean',
        ]);

        $announcement->update($validated);
        return $announcement;
    }

    public function destroy($id)
    {
        \App\Models\Announcement::destroy($id);
        return response()->noContent();
    }
}
