-- Migration des anciennes données vers le nouveau format
-- À exécuter APRÈS avoir ajouté la colonne proof_files via changelog-db.sql
-- Description: Migre les données de proof_file_path vers proof_files (JSON)

BEGIN;

-- Migrer les données existantes
UPDATE proof 
SET proof_files = CASE 
    WHEN proof_file_path IS NOT NULL AND proof_file_path != '' THEN
        jsonb_build_array(
            jsonb_build_object(
                'original_name', proof_file_path,
                'saved_name', proof_file_path,
                'saved_path', 'uploads/' || proof_file_path,
                'file_size', NULL,
                'mime_type', NULL,
                'uploaded_at', created_at::text
            )
        )
    ELSE
        '[]'::jsonb
END
WHERE proof_files IS NULL OR proof_files = '[]'::jsonb;

-- Vérification des résultats
SELECT 
    COUNT(*) as total_justificatifs,
    COUNT(CASE WHEN proof_files = '[]'::jsonb THEN 1 END) as sans_fichier,
    COUNT(CASE WHEN jsonb_array_length(proof_files) > 0 THEN 1 END) as avec_fichiers,
    COUNT(CASE WHEN jsonb_array_length(proof_files) = 1 THEN 1 END) as avec_un_fichier
FROM proof;

-- Afficher un échantillon des données migrées
SELECT 
    id,
    student_id,
    proof_file_path as ancien_champ,
    proof_files as nouveau_champ,
    submitted_at
FROM proof
LIMIT 5;

-- Si tout est OK, décommenter la ligne suivante et exécuter
COMMIT;

-- Si problème détecté, exécuter plutôt :
-- ROLLBACK;