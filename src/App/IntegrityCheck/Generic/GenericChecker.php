<?php

namespace App\IntegrityCheck\Generic;

use App\IntegrityCheck\Support\FileHashInventory;

class GenericChecker
{

	public static function buildInventory(array $task, string $tmpDir): array
	{
		$inventory = FileHashInventory::build(
			$task['folder_root'],
			$task['ignored_files'] ?? [],
			$task['ignored_folders'] ?? []
		);

		FileHashInventory::save($task['generic_inventory_files'], $inventory);

		return [ "Build OK - " . count($inventory['files']) . " fichiers hashés" ];
	}

	public static function check(array $task, string $tmpDir): array
	{
		$baseline = FileHashInventory::load($task['generic_inventory_files']);

		if ($baseline === null)
		{
			return [
				'result' => 'KO',
				'result_strings' => [ "Inventaire introuvable (" . $task['generic_inventory_files'] . "). Lancez la tâche integrity_build_inventory d'abord." ],
				'added_files' => [],
				'added_folders' => [],
				'updated_files' => [],
				'missing_files' => [],
				'missing_folders' => [],
				'database_items_found' => []
			];
		}

		$current = FileHashInventory::build(
			$task['folder_root'],
			$task['ignored_files'] ?? [],
			$task['ignored_folders'] ?? []
		);

		$diff = FileHashInventory::diff(
			$baseline['files'],
			$current['files'],
			$task['folder_group_min_files'] ?? 2,
			$task['ignored_files'] ?? [],
			$task['ignored_folders'] ?? []
		);

		$result = (count($diff['added']) + count($diff['added_folders']) + count($diff['updated']) + count($diff['missing']) + count($diff['missing_folders'])) === 0 ? 'OK' : 'KO';

		return [
			'result' => $result,
			'result_strings' => FileHashInventory::formatDiffStrings($diff),
			'added_files' => $diff['added'],
			'added_folders' => $diff['added_folders'],
			'updated_files' => $diff['updated'],
			'missing_files' => $diff['missing'],
			'missing_folders' => $diff['missing_folders'],
			'database_items_found' => []
		];
	}

}
