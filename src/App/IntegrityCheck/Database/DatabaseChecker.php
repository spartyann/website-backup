<?php

namespace App\IntegrityCheck\Database;

use App\IntegrityCheck\Support\DbConnector;
use PDO;

class DatabaseChecker
{

	public static function check(array $task, string $tmpDir): array
	{
		$words = $task['db_dangerous_words'] ?? [];
		$regexes = $task['db_dangerous_regex'] ?? [];

		if (count($words) === 0 && count($regexes) === 0)
		{
			return [
				'result' => 'OK',
				'result_strings' => [ "Aucun mot-clé ou regex dangereux configuré (db_dangerous_words / db_dangerous_regex) - rien à vérifier." ],
				'added_files' => [],
				'added_folders' => [],
				'updated_files' => [],
				'missing_files' => [],
				'missing_folders' => [],
				'database_items_found' => []
			];
		}

		$pdo = DbConnector::connect($task);
		$dbName = $task['db_name'];
		$ignoredTables = $task['db_ignored_tables'] ?? [];
		$ignoredLines = $task['db_ignored_lines'] ?? [];

		$tables = self::getTextTables($pdo, $dbName, $ignoredTables);

		$database_items_found = [];

		foreach ($tables as $table => $columns)
		{
			$pk = self::getPrimaryKeyColumn($pdo, $dbName, $table);
			$matches = self::scanTable($pdo, $table, $columns, $pk, $words, $regexes);

			foreach ($matches as $match)
			{
				if ($pk !== null && isset($ignoredLines[$table]) && in_array((string)$match['pk_value'], array_map('strval', $ignoredLines[$table])))
				{
					continue;
				}

				$database_items_found[] = $match;
			}
		}

		$result_strings = [];
		foreach ($database_items_found as $item)
		{
			$pkPart = $item['pk_value'] !== null ? " ({$item['pk_column']}={$item['pk_value']})" : '';
			$result_strings[] = "- {$item['table']}{$pkPart} : colonne '{$item['column']}' contient '{$item['trigger']}'";
		}

		$result = count($database_items_found) > 0 ? 'KO' : 'OK';

		return [
			'result' => $result,
			'result_strings' => $result_strings,
			'added_files' => [],
			'added_folders' => [],
			'updated_files' => [],
			'missing_files' => [],
			'missing_folders' => [],
			'database_items_found' => $database_items_found
		];
	}

	// Retourne [ table => [colonne texte, ...] ] pour les tables non ignorées
	private static function getTextTables(PDO $pdo, string $dbName, array $ignoredTables): array
	{
		$textTypes = ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'json'];
		$placeholders = implode(',', array_fill(0, count($textTypes), '?'));

		$stmt = $pdo->prepare(
			"SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = ? AND DATA_TYPE IN ($placeholders)
			 ORDER BY TABLE_NAME, ORDINAL_POSITION"
		);
		$stmt->execute(array_merge([$dbName], $textTypes));

		$tables = [];
		foreach ($stmt->fetchAll() as $row)
		{
			$table = $row['TABLE_NAME'];
			if (in_array($table, $ignoredTables)) continue;

			$tables[$table][] = $row['COLUMN_NAME'];
		}

		return $tables;
	}

	private static function getPrimaryKeyColumn(PDO $pdo, string $dbName, string $table): ?string
	{
		$stmt = $pdo->prepare(
			"SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
			 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' AND ORDINAL_POSITION = 1"
		);
		$stmt->execute([$dbName, $table]);
		$row = $stmt->fetch();

		return $row['COLUMN_NAME'] ?? null;
	}

	private static function scanTable(PDO $pdo, string $table, array $columns, ?string $pk, array $words, array $regexes): array
	{
		$conditions = [];
		$params = [];

		foreach ($columns as $column)
		{
			foreach ($words as $word)
			{
				$conditions[] = "`$column` LIKE ?";
				$params[] = '%' . $word . '%';
			}
			foreach ($regexes as $regex)
			{
				$conditions[] = "`$column` REGEXP ?";
				$params[] = $regex;
			}
		}

		if (count($conditions) === 0) return [];

		$selectCols = array_map(fn($c) => "`$c`", $columns);
		if ($pk !== null) array_unshift($selectCols, "`$pk`");

		$sql = "SELECT " . implode(', ', $selectCols) . " FROM `$table` WHERE " . implode(' OR ', $conditions);

		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);

		$results = [];

		foreach ($stmt->fetchAll() as $row)
		{
			foreach ($columns as $column)
			{
				$value = $row[$column] ?? null;
				if ($value === null) continue;

				foreach ($words as $word)
				{
					if (stripos($value, $word) !== false)
					{
						$results[] = [
							'table' => $table,
							'column' => $column,
							'pk_column' => $pk,
							'pk_value' => $pk !== null ? $row[$pk] : null,
							'trigger' => $word,
						];
					}
				}

				foreach ($regexes as $regex)
				{
					// Evaluation PCRE locale en best-effort pour identifier la colonne responsable du match SQL REGEXP (moteurs proches mais pas garantis identiques)
					if (self::testRegex($regex, $value))
					{
						$results[] = [
							'table' => $table,
							'column' => $column,
							'pk_column' => $pk,
							'pk_value' => $pk !== null ? $row[$pk] : null,
							'trigger' => $regex,
						];
					}
				}
			}
		}

		return $results;
	}

	private static function testRegex(string $pattern, string $value): bool
	{
		$delimited = '/' . str_replace('/', '\/', $pattern) . '/i';
		return @preg_match($delimited, $value) === 1;
	}

}
