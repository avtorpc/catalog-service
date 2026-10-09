<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Infrastructure\DB\SchemaSqlHelper;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

final class VacancyService
{
    public function __construct(private Connection $db, private SchemaSqlHelper $schema, private WorkspaceService $workspace) {}

    public function get(array $identity, string $id): array
    {
        $this->employer($identity);
        $this->uuid($id);
        $row = $this->db->fetchAssociative('SELECT * FROM '.$this->schema->table('vacancies').' WHERE id=? AND employer_id=?', [$id, $identity['id']]);
        if (!$row) throw new NotFoundHttpException('Вакансия не найдена.');
        foreach (['content', 'optional_assignment'] as $field) {
            if (is_string($row[$field])) $row[$field] = json_decode($row[$field], true, 512, JSON_THROW_ON_ERROR);
        }
        return $row;
    }

    public function save(array $identity, array $input, string $token): array
    {
        $this->employer($identity);
        if (array_diff(array_keys($input), ['id', 'version', 'title', 'content'])) throw new UnprocessableEntityHttpException('В форме есть неизвестные поля.');
        $id = $input['id'] ?? null;
        $this->uuid($id);
        $version = $input['version'] ?? null;
        if (!is_int($version) || $version < 0) throw new UnprocessableEntityHttpException('Обновите форму вакансии.');
        $title = $input['title'] ?? null;
        if (!is_string($title) || trim($title) === '' || mb_strlen(trim($title)) > 150) throw new UnprocessableEntityHttpException('Укажите название вакансии длиной до 150 символов.');
        $title = trim($title);
        $content = $this->content($input['content'] ?? null);
        $this->workspace->initialize($identity, $token);
        $table = $this->schema->table('vacancies');
        $json = json_encode($content, JSON_THROW_ON_ERROR);
        if ($version === 0) {
            $inserted = $this->db->executeStatement("INSERT INTO {$table}(id,employer_id,title,content) VALUES(?,?,?,CAST(? AS JSONB)) ON CONFLICT(id) DO NOTHING", [$id, $identity['id'], $title, $json]);
            if (!$inserted) {
                $existing = $this->get($identity, $id);
                if ($existing['title'] !== $title || $existing['content'] != $content || (int)$existing['version'] !== 1 || $existing['status'] !== 'draft') throw new ConflictHttpException('Черновик уже изменён. Откройте его из списка вакансий.');
            }
        } else {
            $existing = $this->get($identity, $id);
            if ($existing['status'] !== 'draft') throw new ConflictHttpException('Сейчас можно редактировать только черновики.');
            $updated = $this->db->executeStatement("UPDATE {$table} SET title=?,content=CAST(? AS JSONB),version=version+1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND employer_id=? AND version=? AND status='draft'", [$title, $json, $id, $identity['id'], $version]);
            if (!$updated) throw new ConflictHttpException('Вакансия изменена в другой вкладке. Обновите страницу перед сохранением.');
        }
        return $this->get($identity, $id);
    }

    public function publication(array $identity, string $id, array $input, bool $publish): array
    {
        $row = $this->get($identity, $id);
        if (array_keys($input) !== ['version'] || !is_int($input['version']) || $input['version'] < 1) throw new UnprocessableEntityHttpException('Обновите список вакансий.');
        if ((int)$row['version'] !== $input['version']) throw new ConflictHttpException('Вакансия изменена. Обновите страницу.');
        $target = $publish ? 'published' : 'draft';
        if ($row['status'] === $target) return $row;
        if ($row['status'] !== ($publish ? 'draft' : 'published')) throw new ConflictHttpException('Недоступный статус вакансии.');
        if ($publish) {
            $content = $this->content($row['content']);
            $labels = ['specialty'=>'специальность','experience'=>'опыт','format'=>'формат работы','employment'=>'занятость','description'=>'описание','tasks'=>'задачи','skills'=>'навыки','requirements'=>'требования','company'=>'компания','contact_name'=>'контактное лицо','contact_email'=>'контактный email'];
            $missing = [];
            foreach ($labels as $key=>$label) if ($content[$key] === '' || (in_array($key, ['skills','requirements'], true) && !preg_match('/[\p{L}\p{N}]/u', $content[$key]))) $missing[] = $label;
            if (in_array($content['format'], ['office','hybrid'], true) && !preg_match('/[\p{L}\p{N}]/u', $content['cities'])) $missing[] = 'город';
            if (!$content['salary_negotiable'] && $content['salary_from'] === '' && $content['salary_to'] === '') $missing[] = 'доход или «по договорённости»';
            if ($missing) throw new UnprocessableEntityHttpException('Для публикации заполните: '.implode(', ', $missing).'.');
        }
        $table = $this->schema->table('vacancies');
        $date = $publish ? 'CURRENT_TIMESTAMP' : 'NULL';
        $updated = $this->db->executeStatement("UPDATE {$table} SET status=?,published_at={$date},version=version+1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND employer_id=? AND version=? AND status=?", [$target,$id,$identity['id'],$input['version'],$row['status']]);
        if (!$updated) throw new ConflictHttpException('Вакансия изменена. Обновите страницу.');
        return $this->get($identity, $id);
    }

    private function content(mixed $input): array
    {
        $limits = ['specialty'=>150,'experience'=>100,'format'=>30,'employment'=>50,'cities'=>500,'salary_from'=>15,'salary_to'=>15,'schedule'=>200,'description'=>5000,'tasks'=>5000,'skills'=>1000,'requirements'=>5000,'benefits'=>5000,'company'=>150,'contact_name'=>100,'contact_email'=>254];
        if (!is_array($input) || array_diff(array_keys($input), [...array_keys($limits), 'salary_negotiable'])) throw new UnprocessableEntityHttpException('Проверьте поля вакансии.');
        $result = [];
        foreach ($limits as $field=>$limit) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || mb_strlen(trim($value)) > $limit) throw new UnprocessableEntityHttpException('Проверьте длину полей вакансии.');
            $result[$field] = trim($value);
        }
        if (!in_array($result['format'], ['', 'office', 'hybrid', 'remote'], true) || !in_array($result['employment'], ['', 'full', 'part', 'project', 'internship'], true)) throw new UnprocessableEntityHttpException('Выберите формат работы и занятость из списка.');
        if ($result['contact_email'] !== '' && !filter_var($result['contact_email'], FILTER_VALIDATE_EMAIL)) throw new UnprocessableEntityHttpException('Укажите корректный контактный email.');
        $result['salary_negotiable'] = $input['salary_negotiable'] ?? false;
        if (!is_bool($result['salary_negotiable'])) throw new UnprocessableEntityHttpException('Проверьте условия дохода.');
        if ($result['salary_negotiable']) {
            $result['salary_from'] = $result['salary_to'] = '';
        } else {
            foreach (['salary_from', 'salary_to'] as $field) if ($result[$field] !== '' && (!preg_match('/^\d{1,12}$/D', $result[$field]))) throw new UnprocessableEntityHttpException('Доход должен быть целой неотрицательной суммой.');
            if ($result['salary_from'] !== '' && $result['salary_to'] !== '' && (float)$result['salary_to'] < (float)$result['salary_from']) throw new UnprocessableEntityHttpException('Верхняя граница дохода не может быть ниже нижней.');
        }
        return $result;
    }

    private function employer(array $identity): void
    {
        if ($identity['role'] !== 'employer') throw new AccessDeniedHttpException('Вакансии доступны работодателю.');
    }

    private function uuid(mixed $id): void
    {
        if (!is_string($id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $id)) throw new UnprocessableEntityHttpException('Некорректный идентификатор вакансии.');
    }
}
