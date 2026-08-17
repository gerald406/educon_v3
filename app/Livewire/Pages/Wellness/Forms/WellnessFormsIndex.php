<?php

namespace App\Livewire\Pages\Wellness\Forms;

use App\Models\Wellness\SurveyForm;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On; // <-- Importación necesaria para escuchar eventos

class WellnessFormsIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $formType = '';
    public $status = '';
    public $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'formType' => ['except' => ''],
        'status' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        // Usamos la institución vinculada al usuario
        $institutionId = Auth::user()->institution_id ?? 1;

        $forms = SurveyForm::query()
            ->where('institution_id', $institutionId)
            ->withCount('responses') // Útil para saber cuántas respuestas tiene
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->formType, function ($query) {
                $query->where('form_type', $this->formType);
            })
            ->when($this->status !== '', function ($query) {
                $query->where('is_active', $this->status === 'active');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.pages.wellness.forms.wellness-forms-index', [
            'forms' => $forms,
        ])->layout('layouts.app');
    }

    #[On('delete')] // <-- Decorador que enlaza el evento JS con este método PHP
    public function delete($id)
    {
        $form = SurveyForm::withCount('responses')->findOrFail($id);

        // Regla de Negocio: No eliminar si tiene respuestas
        if ($form->responses_count > 0) {
            $this->dispatch('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'No se puede eliminar un formulario que ya tiene respuestas.']);
            return;
        }

        $form->delete();
        $this->dispatch('swal', ['icon' => 'success', 'title' => '¡Eliminado!', 'text' => 'El formulario fue eliminado correctamente.']);
    }

    public function toggleStatus($id)
    {
        $form = SurveyForm::findOrFail($id);
        $form->is_active = !$form->is_active;
        $form->save();
        $this->dispatch('swal', ['icon' => 'success', 'title' => 'Estado actualizado', 'text' => 'El estado cambió a ' . ($form->is_active ? 'Activo' : 'Inactivo')]);
    }
}
