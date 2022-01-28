import argparse
from pathlib import Path
from pycoingecko import CoinGeckoAPI
import json
import random
import time
import urllib3


def get_parser():
    parser = argparse.ArgumentParser(description='Pixcoins grabber')
    group = parser.add_mutually_exclusive_group()
    group.add_argument('-m', '--make', nargs=1, metavar='name',
                       type=str, help='Make a pack from start to end')
    group.add_argument('-k', '--known-json', action='store_true',
                       help='Generate known.json from already generated packs')
    group.add_argument('-c', '--csv', nargs=1, metavar='name',
                       type=str, help='Generate csv from start to end')
    return parser


def wait_if_needed(start_time, requests_done):
    # max time between two requests: 60/50 = 1.2
    min_expected_time = requests_done * 1.2
    elapsed_time = time.time() - start_time
    if elapsed_time < min_expected_time:
        # print('too fast, pausing!')
        time.sleep(min_expected_time - elapsed_time)


def make_pack(name):
    pack_name = f'pack-{name}'
    pack_path = Path('packs', pack_name)
    if pack_path.is_dir():
        print(pack_path.as_posix(), 'already exists')
        # exit(1)

    print('Creating', pack_path.as_posix())
    if not pack_path.exists():
        pack_path.mkdir(parents=True)
    known_json = Path('packs', 'known.json').open('r')
    known_list = json.load(known_json)
    known_json.close()
    cg = CoinGeckoAPI()
    coin_list = cg.get_coins_list()
    not_known_coin_list = [coin for coin in coin_list if coin['id'] not in known_list]
    # not_known_coin_list = list(filter(lambda coin: coin['id'] not in coin_list, known_list))
    pack_crypto_list = random.sample(not_known_coin_list, min(len(not_known_coin_list), 100))
    start_time = time.time()
    requests_done = 1
    http = urllib3.PoolManager()
    for coin in pack_crypto_list:
        wait_if_needed(start_time, requests_done)
        coin_details = cg.get_coin_by_id(coin['id'])
        print(f'Got coin {coin["id"]}')
        requests_done += 1
        url = coin_details['image']['small']
        ico_extension = get_file_extension_from_url(url)
        file_name = f'{coin["id"]}.{ico_extension}'
        wait_if_needed(start_time, requests_done)
        r = http.request('GET', url)
        requests_done += 1
        with Path(pack_path, file_name).open('wb') as file:
            file.write(r.data)

    # TODO: finish function


def get_file_extension_from_url(url: str) -> str | None:
    if url.find('/'):
        name, _ = url.rsplit('/', 1)[1].split('?')
        return name.rsplit('.', 1)[1]
    return None


def generate_known_json():
    known_list = []
    for pack in Path('packs').iterdir():
        if pack.is_dir():
            for ico_file in pack.iterdir():
                ico_name = ico_file.name
                ico_extension = ico_file.suffix
                if ico_extension == 'csv':
                    continue
                known_list.append(ico_name)
    with Path('packs', 'known.json').open('w') as known_json:
        json.dump(known_list, known_json)


def generate_csv(name: str):
    print(f'start csv {name}')


def main():
    parser = get_parser()
    args = parser.parse_args()
    if args.make:
        make_pack(args.make[0])
    elif args.known_json:
        generate_known_json()
    elif args.csv:
        generate_csv(args.csv[0])
    else:
        parser.error(message='No valid argument provided')


if __name__ == '__main__':
    main()
