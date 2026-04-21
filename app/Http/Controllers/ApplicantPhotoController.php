<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use Illuminate\Support\Facades\Storage;

class ApplicantPhotoController extends Controller
{
    /**
     * Muestra la foto del postulante basándose en su DNI (code).
     *
     * @param string $dni
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
     */
    public function show($dni)
    {
        // 1. Buscar al postulante por su código (DNI). Si no existe, lanza un 404.
        $applicant = Applicant::where('code', $dni)->firstOrFail();

        // 2. Verificar si el postulante tiene una URL de foto registrada.
        if (empty($applicant->photo_url)) {
            abort(404, 'El postulante no tiene una foto registrada.');
        }

        // 3. Caso A: Si la foto es un enlace externo (AWS S3, Cloudinary, etc.)
        if (filter_var($applicant->photo_url, FILTER_VALIDATE_URL)) {
            return redirect($applicant->photo_url);
        }

        // 4. Caso B: Si la foto está almacenada localmente (usualmente en el disco 'public')
        // Verifica en qué disco estás guardando las fotos. Por defecto en Laravel suele ser 'public'.
        $path = Storage::disk('public')->path($applicant->photo_url);

        if (!file_exists($path)) {
            abort(404, 'El archivo físico de la foto no fue encontrado en el servidor.');
        }

        // 5. Retornar el archivo directamente al navegador para su visualización
        return response()->file($path);
    }
}