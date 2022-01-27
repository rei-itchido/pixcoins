<?php
error_reporting(E_ALL & ~E_WARNING);

require "vendor/autoload.php";

use Codenixsv\CoinGeckoApi\CoinGeckoClient;

$doc = <<<DOC
Usage:
  run.php make <pack>
  run.php known-json
   
Commands:
  make                 Make a pack with rank passed as arg 
  known-json           Generate known.json from already generated packs
  known-json           Generate known.json from already generated packs

Options:
  pack                 Pack rank

DOC;

$args = Docopt::handle($doc);

if ($args['make']) {
  make($args['<pack>']);
}
elseif ($args['known-json']) {
  generateKnownJson();
}
elseif ($args['generate-csv']) {
  generatePackCsvFromPackImages($args['<pack>']);
}

/*** run functions ***/


function make($pack) {
  $packName = 'pack' . $pack;
  $packPath = 'packs/' . $packName;

  if (file_exists($packPath)) {
    echo $packPath . " already exists...";
    exit();
  }

  mkdir($packPath);

  $client = new CoinGeckoClient();
  $apiCoinList = $client->coins()->getList();
  $knownList = json_decode(file_get_contents('packs/known.json'), true);
  $maxApiCoinListIndex = count($apiCoinList) - 1;
  $packCryptoList = [];
  for ($i = 1; $i <= 100; $i++) {
    echo $i . PHP_EOL;
    $coin = getNotKnownCoin($client, $apiCoinList, $knownList, $maxApiCoinListIndex);
    if($coin === false) {
      $i--;
      continue;
    }
    $icoExtension = getIcoExtensionFromUrl($coin['image']['small']);
    $newIcoPath = $packPath . '/' . $coin['id'] . '.' . $icoExtension;
    $image = file_get_contents($coin['image']['small']);
    if($image === false) {
      $i--;
      continue;
    }
    $packCryptoList[$coin['id']] = [
      'name' => $coin['name'],
      'icoFilename' => $coin['image']['small'],
    ];
    file_put_contents($newIcoPath, $image);
    echo $coin['id'] . ' OK' . PHP_EOL;
  }
  generatePackCsv($packPath, $pack, $packCryptoList);
  generateKnownJson();
}

function getNotKnownCoin(CoinGeckoClient $client, $apiCoinList, $knownList, $maxApiCoinListIndex) {
  $notFound = false;
  while (!$notFound) {
    $rand = random_int(0, $maxApiCoinListIndex);
    if (!in_array($apiCoinList[$rand]['id'], $knownList, true)) {
      $notFound = true;
    }
  }

  try {
    $coin = $client->coins()->getCoin($apiCoinList[$rand]['id']);
  } catch (Exception $e) {
    echo "Error with " . $apiCoinList[$rand]['id'] . ' (' . $e->getCode() . ') tryng with another...' . PHP_EOL;
    sleep(10);
    return false;
  }
  return $coin;
}

function generateKnownJson() {
  $knownList = [];
  if ($packsHandle = opendir('packs')) {
    while (false !== ($packName = readdir($packsHandle))) {
      if ($packName !== "." && $packName !== ".." && is_dir('packs/' . $packName)) {
        //        echo PHP_EOL . "*** " . $packName . PHP_EOL . PHP_EOL;
        $packHandle = opendir('packs/' . $packName);
        while (false !== ($icoName = readdir($packHandle))) {
          if ($icoName !== "." && $icoName !== "..") {
            $icoNameExplode = explode('.', $icoName);
            $extension = end($icoNameExplode);
            $cryptoId = str_replace('.' . $extension, '', $icoName);
            if ($extension === 'csv') {
              continue;
            }
            //            echo $cryptoId . PHP_EOL;
            $knownList[] = $cryptoId;
          }
        }
        closedir($packHandle);
      }
    }
    closedir($packsHandle);
  }

  $json = json_encode($knownList);
  file_put_contents('packs/known.json', $json);
}

function generatePackCsv($packPath, $pack, $packCryptoList) {
  ksort($packCryptoList);
  // pack_0 1 to 169
  $firstId = ($pack == 0) ? 1 : $pack * 100 + (75 + 1);
  $rank = $firstId;
  $csvDataList = [];
  foreach ($packCryptoList as $cryptoId => $packCrypto) {
    $csvDataList[] = [
      str_pad($rank, 4, 0, STR_PAD_LEFT),
      $cryptoId,
      $packCrypto['name'],
      $pack,
    ];
    $rank++;
  }

  $csvName = 'pack_' . $pack . '.csv';

  $fp = fopen($packPath . '/' . $csvName, 'w');
  $headers = ['#', 'id', 'full name', 'pack'];
  fputcsv($fp, $headers, ';');
  foreach ($csvDataList as $csvData) {
    fputcsv($fp, $csvData, ';');
  }
  fclose($fp);
}

function generatePackCsvFromPackImages($pack) {
  exit();
  $packName = 'pack' . $pack;
  $packPath = 'packs/' . $packName;

  if (!file_exists($packPath)) {
    echo $packPath . " doesn't exists...";
    exit();
  }

  $packCryptoList = [];
  $client = new CoinGeckoClient();
  foreach (scandir($packPath) as $item) {
    if ($item == '.' || $item == '..') {
      continue;
    }
    $coinId = getCoinIdFromImageName($item);
    $coin = $client->coins()->getCoin($coinId);
    $packCryptoList[$coin['id']] = [
      'name' => $coin['name'],
      'icoFilename' => $coin['image']['small'],
    ];
  }
  $a=1;
  // generatePackCsv($packPath, $pack, $packCryptoList);
}

function deleteDirectory($dir): bool {
  if (!file_exists($dir)) {
    return true;
  }

  if (!is_dir($dir)) {
    return unlink($dir);
  }

  foreach (scandir($dir) as $item) {
    if ($item == '.' || $item == '..') {
      continue;
    }

    if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
      return false;
    }

  }
  return rmdir($dir);
}

function getIcoExtensionFromUrl($icoUrl): string {
  $urlExplode = explode('.', $icoUrl);
  $extension = end($urlExplode);
  return substr($extension, 0, strrpos($extension, '?'));
}

function getIcoExtensionFromName($imageName) {
  $urlExplode = explode('.', $imageName);
  $extension = end($urlExplode);
  return $extension;
}


function getCoinIdFromImageName($imageName) {
  $extension = getIcoExtensionFromName($imageName);
  return str_replace("." . $extension, '', $imageName);
}