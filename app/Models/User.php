<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;

// [IMPORTACIÓN CLAVE]
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles; // <-- [USO CLAVE DEL TRAIT]

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'lastname', // Nuevo
        'full_name', // Nuevo (opcional si usas Accessors, pero está en tu tabla)
        'email',
        'password',
        'document_number',
        // (user_type ya fue eliminado)
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];


    /**
     * Determina si el usuario tiene acceso al panel administrativo/staff
     */
    public function hasAdminAccess(): bool
    {
        // 1. Los Administradores SIEMPRE ven el panel lateral (Sidebar)
        if ($this->hasRole('Administrador')) {
            return true;
        }

        // 2. Si es estrictamente un Docente o Coordinador (y no es Admin), 
        // forzamos a que NO vea el Sidebar para liberar su pantalla completa.
        if ($this->hasAnyRole(['Coordinador', 'Docente'])) {
            return false;
        }

        // 3. Lógica original para otros roles del sistema (ej. Admisión, Tesorería)
        $adminPermissions = [
            'gestionar-institucion',
            'gestionar-configuracion',
            'gestionar-estructura-academica',
            'gestionar-prerrequisitos',
            'gestionar-docentes',
            'gestionar-estudiantes',
            'gestionar-periodos',
            'gestionar-carga-academica',
            'gestionar-horarios',
            'aprobar-silabos',
            'registrar-pagos',
            'gestionar-sesiones-caja',
            'gestionar-correlativos',
            'registrar-tramites',
            'anular-comprobantes',
            'gestionar-certificacion',
            'gestionar-cuadro-meritos',
            'gestionar-biblioteca',
            'gestionar-admision',
            'gestionar-anuncios',
            'gestionar-reservas-matricula',
            'gestionar-reincorporaciones',
            'gestionar-matricula-regular',
            'gestionar-roles',
            'gestionar-usuarios',
        ];

        return $this->hasAnyPermission($adminPermissions);
    }

    /**
     * Determina si el usuario usa la barra de navegación superior académica
     */
    public function isTeacher(): bool
    {
        // Si tiene cualquiera de estos dos roles, SIEMPRE verá sus enlaces superiores
        return $this->hasAnyRole(['Docente', 'Coordinador']);
    }

    /**
     * Determina si el usuario es EXCLUSIVAMENTE un estudiante
     * (NO un usuario administrativo)
     */
    public function isStudent(): bool
    {
        // Si tiene acceso admin, NO es un estudiante
        if ($this->hasAdminAccess()) {
            return false;
        }

        // Solo si NO es admin Y tiene permisos de estudiante
        return $this->hasAnyPermission([
            'matricularse',
            'entregar-actividades',
            'ver-mis-asistencias',
        ]);
    }

    /**
     * Obtiene el perfil de docente asociado al usuario.
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }
    
    /**
     * Obtiene el perfil de estudiante asociado al usuario.
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }
    /**
     * Obtiene el perfil de postulante asociado al usuario.
     */
    public function applicant()
    {
        return $this->hasOne(Applicant::class);
    }

    // AÑADIR en User.php junto a las demás relaciones

    /**
     * Obtiene la asignación de coordinación del usuario.
     * Un coordinador solo coordina UNA carrera.
     */
    public function careerCoordinator(): HasOne
    {
        return $this->hasOne(CareerCoordinator::class);
    }

    /**
     * Determina si el usuario es coordinador activo de alguna carrera.
     */
    public function isCoordinator(): bool
    {
        return $this->careerCoordinator()->where('is_active', true)->exists();
    }

    /**
     * Obtiene la carrera que coordina este usuario (si aplica).
     */
    public function coordinatedCareer(): ?Career
    {
        return $this->careerCoordinator?->career ?? null;
    }
}