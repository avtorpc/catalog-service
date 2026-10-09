<?php
declare(strict_types=1);
namespace App\Application\Workspace;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Ramsey\Uuid\Uuid;
final class ResumePdfStorage
{
 public function __construct(#[Autowire('%env(CATALOG_UPLOAD_DIR)%')] private string $baseDir) {}
 public function save(UploadedFile $file):array {
  if($file->getError()!==UPLOAD_ERR_OK)throw new \InvalidArgumentException('Ошибка передачи файла (код '.$file->getError().').');
  if($file->getSize()<=0)throw new \InvalidArgumentException('Файл пустой или не был передан.');
  $header=file_get_contents($file->getPathname(),false,null,0,5);
  if($file->getSize()>10*1024*1024)throw new \InvalidArgumentException('Файл больше 10 МБ.');
  if($header!=='%PDF-')throw new \InvalidArgumentException('Файл имеет расширение PDF, но его содержимое не распознано как PDF.');
  $dir=rtrim($this->baseDir,'/').'/resumes';if(!is_dir($dir))mkdir($dir,0775,true);$name=Uuid::uuid4()->toString().'.pdf';$file->move($dir,$name);return ['original_name'=>$file->getClientOriginalName(),'storage_name'=>$name,'mime_type'=>'application/pdf','size'=>filesize($dir.'/'.$name)];
 }
 public function remove(string $storageName):void { if($storageName==='')return; $path=rtrim($this->baseDir,'/').'/resumes/'.$storageName;if(is_file($path))unlink($path); }
}
