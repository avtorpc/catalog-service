<?php

declare(strict_types=1);
namespace App\Application\Workspace;

use App\Infrastructure\DB\SchemaSqlHelper;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicVacancyService
{
    public function __construct(private Connection $db, private SchemaSqlHelper $schema) {}

    public function list(): array
    {
        $rows = $this->db->fetchAllAssociative('SELECT id,title,content,published_at FROM '.$this->schema->table('vacancies')." WHERE status='published' AND published_at IS NOT NULL ORDER BY published_at DESC,id DESC");
        return array_map($this->present(...), $rows);
    }

    public function get(string $id): array
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $id)) throw new NotFoundHttpException('Вакансия не найдена.');
        $row = $this->db->fetchAssociative('SELECT id,title,content,published_at FROM '.$this->schema->table('vacancies')." WHERE id=? AND status='published' AND published_at IS NOT NULL", [$id]);
        if (!$row) throw new NotFoundHttpException('Вакансия не найдена.');
        return $this->present($row);
    }

    private function present(array $row): array
    {
        $c = json_decode($row['content'], true, 512, JSON_THROW_ON_ERROR);
        $split = static fn(string $text, string $pattern): array => array_values(array_filter(array_map('trim', preg_split($pattern, $text)), static fn($value)=>$value!==''));
        $range = ($c['salary_negotiable'] ?? false) ? 'По договорённости' : trim((($c['salary_from'] ?? '')!=='' ? 'от '.number_format((float)$c['salary_from'], 0, ',', ' ').' ' : '').(($c['salary_to'] ?? '')!=='' ? 'до '.number_format((float)$c['salary_to'], 0, ',', ' ').' ' : '')).' ₽';
        return [
            'id'=>$row['id'],'key'=>'vacancy:'.$row['id'],'title'=>$row['title'],
            'company'=>$c['company']??'','logo'=>mb_substr($c['company']??'',0,1),
            'publishedAt'=>(new \DateTimeImmutable($row['published_at']))->setTimezone(new \DateTimeZone('Europe/Moscow'))->format('Y-m-d'),
            'publishedTimestamp'=>$row['published_at'],'specialty'=>$c['specialty']??'',
            'salary'=>(float)(($c['salary_from']??'') ?: ($c['salary_to']??0)),'range'=>$range,
            'city'=>($c['cities']??'') ?: 'Любой город','cities'=>$split($c['cities']??'', '/,/u'),
            'format'=>['office'=>'В офисе','hybrid'=>'Гибрид','remote'=>'Удалённо'][$c['format']??'']??'',
            'employment'=>['full'=>'Полная','part'=>'Частичная','project'=>'Проектная','internship'=>'Стажировка'][$c['employment']??'']??'',
            'experience'=>$c['experience']??'','text'=>$c['description']??'','tasks'=>$c['tasks']??'',
            'skills'=>$split($c['skills']??'', '/[,\n]/u'),'requirements'=>$split($c['requirements']??'', '/[\r\n]+/u'),
            'benefits'=>$c['benefits']??'','schedule'=>$c['schedule']??'','task'=>null,
        ];
    }
}
