<?php

namespace App\Http\Controllers;

use App\Models\ProjectCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ProjectCategory::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:project_categories,id'],
        ]);

        if (! empty($validated['parent_id'])) {
            $parent = ProjectCategory::findOrFail($validated['parent_id']);

            if ($parent->parent_id !== null) {
                return back()->withErrors([
                    'parent_id' => 'Sub-categories can only be one level deep.',
                ])->withInput();
            }
        }

        $exists = ProjectCategory::where('parent_id', $validated['parent_id'] ?? null)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['name'])])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'name' => 'A category with this name already exists at this level.',
            ])->withInput();
        }

        ProjectCategory::create([
            'name'      => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        return redirect()->route('categories.index')->with('success', 'Category added.');
    }

    public function update(Request $request, ProjectCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $exists = ProjectCategory::where('parent_id', $category->parent_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['name'])])
            ->where('id', '!=', $category->id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'name' => 'A category with this name already exists at this level.',
            ])->withInput();
        }

        $category->update(['name' => $validated['name']]);

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function destroy(ProjectCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Category removed.');
    }
}
