<?php
declare(strict_types=1);

/** @return int|null */
function calculate_age_from_dob(?string $dob): ?int
{
    if ($dob === null || trim($dob) === '') {
        return null;
    }
    try {
        $birth = new DateTimeImmutable(trim($dob));
        $today = new DateTimeImmutable('today');

        return (int) $birth->diff($today)->y;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Resolve age and optional DOB from registration input.
 *
 * @return array{date_of_birth: ?string, age: int}|array{error: string}
 */
function resolve_registration_age_dob(?string $dobRaw, mixed $ageRaw): array
{
    $dob = trim((string) ($dobRaw ?? ''));
    $dob = $dob === '' ? null : $dob;
    $ageInput = trim((string) ($ageRaw ?? ''));
    $ageManual = $ageInput === '' ? null : (int) $ageInput;

    if ($dob !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        return ['error' => 'Invalid date of birth. Use YYYY-MM-DD.'];
    }

    if ($dob !== null) {
        $computed = calculate_age_from_dob($dob);
        if ($computed === null || $computed < 0 || $computed > 120) {
            return ['error' => 'Invalid date of birth.'];
        }

        return ['date_of_birth' => $dob, 'age' => $computed];
    }

    if ($ageManual === null) {
        return ['error' => 'Enter the patient age or date of birth.'];
    }
    if ($ageManual < 1 || $ageManual > 120) {
        return ['error' => 'Age must be between 1 and 120.'];
    }

    return ['date_of_birth' => null, 'age' => $ageManual];
}

/** @return int|null */
function patient_display_age(?array $patient): ?int
{
    if ($patient === null) {
        return null;
    }
    $dob = isset($patient['date_of_birth']) ? (string) $patient['date_of_birth'] : '';
    if ($dob !== '') {
        $fromDob = calculate_age_from_dob($dob);
        if ($fromDob !== null) {
            return $fromDob;
        }
    }
    if (isset($patient['age']) && $patient['age'] !== null && $patient['age'] !== '') {
        $age = (int) $patient['age'];
        if ($age > 0 && $age <= 120) {
            return $age;
        }
    }

    return null;
}
