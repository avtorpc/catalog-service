<?php
require dirname(__DIR__).'/vendor/autoload.php';
use Doctrine\DBAL\{DriverManager,Tools\DsnParser};
$url=getenv('DATABASE_URL');$params=(new DsnParser(['postgresql'=>'pdo_pgsql']))->parse($url);$admin=DriverManager::getConnection($params);$name='chat_transfer_'.bin2hex(random_bytes(5));$db=null;
try{
 $admin->executeStatement('CREATE DATABASE '.$name);$params['dbname']=$name;$db=DriverManager::getConnection($params);
 $db->executeStatement('CREATE SCHEMA catalog');
 $db->executeStatement('CREATE TABLE catalog.applications(id uuid PRIMARY KEY)');
 $db->executeStatement('CREATE TABLE catalog.conversations(id uuid PRIMARY KEY,candidate_id uuid,employer_id uuid,application_id uuid REFERENCES catalog.applications(id))');
 $db->executeStatement('CREATE TABLE catalog.messages(id uuid PRIMARY KEY,conversation_id uuid REFERENCES catalog.conversations(id),body text)');
 $id='00000000-0000-4000-8000-000000000001';$db->executeStatement('INSERT INTO catalog.applications VALUES(?)',[$id]);$db->executeStatement('INSERT INTO catalog.conversations(id,application_id) VALUES(?,?)',[$id,$id]);$db->executeStatement('INSERT INTO catalog.messages VALUES(?,?,?)',[$id,$id,'History preserved']);
 require dirname(__DIR__).'/migrations/Version20261009160000.php';
 $m=new DoctrineMigrations\Version20261009160000($db,new Psr\Log\NullLogger());$m->up(new Doctrine\DBAL\Schema\Schema());foreach($m->getSql() as $sql)$db->executeStatement($sql->getStatement());
 if($db->fetchOne('SELECT body FROM node.messages WHERE id=?',[$id])!=='History preserved'||$db->fetchOne("SELECT to_regclass('catalog.messages')")!==null)throw new RuntimeException('Transfer failed');
 $db->executeStatement('DELETE FROM catalog.applications WHERE id=?',[$id]); // Domain FK removed, chat preserved.
 try{$db->executeStatement('DELETE FROM node.conversations WHERE id=?',[$id]);throw new RuntimeException('Internal chat FK lost');}catch(Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException){}
 echo "PASS: migration preserves IDs, messages and internal foreign key; removes cross-service dependency\n";
}finally{if($db)$db->close();$admin->executeStatement('DROP DATABASE IF EXISTS '.$name.' WITH(FORCE)');$admin->close();}
