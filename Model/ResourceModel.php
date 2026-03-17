<?php

declare(strict_types=1);

/**
 * ResourceModel - Manages resources (subjects) and rooms.
 * Used by the secretary dashboard and related API endpoints.
 */

require_once __DIR__ . '/database.php';

class ResourceModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? getDatabase();
    }

    // Search resources by code or label (ILIKE)
    public function searchResources(string $query): array
    {
        return $this->db->select(
            'SELECT id, code, label, teaching_type
             FROM resources
             WHERE code ILIKE :query OR label ILIKE :query
             ORDER BY label
             LIMIT 20',
            [':query' => '%' . $query . '%']
        );
    }

    // Check if a resource with the given code already exists
    public function resourceExists(string $code): bool
    {
        return $this->db->selectOne(
            'SELECT id FROM resources WHERE code = :code',
            [':code' => $code]
        ) !== null;
    }

    // Create a new resource and return its data
    public function createResource(string $code, string $label): ?array
    {
        return $this->db->selectOne(
            'INSERT INTO resources (code, label) VALUES (:code, :label)
             RETURNING id, code, label, teaching_type',
            [':code' => $code, ':label' => $label]
        );
    }

    // Search rooms by code (ILIKE)
    public function searchRooms(string $query): array
    {
        return $this->db->select(
            'SELECT id, code
             FROM rooms
             WHERE code ILIKE :query
             ORDER BY code
             LIMIT 20',
            [':query' => '%' . $query . '%']
        );
    }

    // Check if a room with the given code already exists
    public function roomExists(string $code): bool
    {
        return $this->db->selectOne(
            'SELECT id FROM rooms WHERE code = :code',
            [':code' => $code]
        ) !== null;
    }

    // Create a new room and return its data
    public function createRoom(string $code): ?array
    {
        return $this->db->selectOne(
            'INSERT INTO rooms (code) VALUES (:code) RETURNING id, code',
            [':code' => $code]
        );
    }
}
