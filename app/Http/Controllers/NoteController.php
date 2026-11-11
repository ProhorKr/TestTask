<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListNotesRequest;
use App\Http\Requests\SaveNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\Response;

class NoteController extends Controller
{
    public function index(ListNotesRequest $request)
    {
        return NoteResource::collection(
            Note::query()->orderByDesc('id')->paginate($request->validated('per_page', 10)),
        );
    }

    public function store(SaveNoteRequest $request)
    {
        $note = Note::create($request->validated());

        return (new NoteResource($note))->response()->setStatusCode(201)
            ->header('Location', url('/api/notes/'.$note->id));
    }

    public function show(string $id): NoteResource
    {
        return new NoteResource(Note::findOrFail($id));
    }

    public function update(SaveNoteRequest $request, string $id): NoteResource
    {
        // SaveNoteRequest validates fields; the route middleware validates the identifier.
        $note = Note::findOrFail($id);
        $note->update($request->validated());

        return new NoteResource($note);
    }

    public function destroy(string $id): Response
    {
        Note::findOrFail($id)->delete();

        return response()->noContent();
    }
}
