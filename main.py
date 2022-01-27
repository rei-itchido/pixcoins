import argparse
from pathlib import Path
from pycoingecko import CoinGeckoAPI
import json
import random
import time


def get_parser():
    parser = argparse.ArgumentParser(description='Pixcoins grabber')
    group = parser.add_mutually_exclusive_group()
    group.add_argument('-m', '--make', nargs=2, metavar=('start', 'end'),
                       type=int, help='Make a pack from start to end')
    group.add_argument('-k', '--known-json', action='store_true',
                       help='Generate known.json from already generated packs')
    group.add_argument('-c', '--csv', nargs=2, metavar=('start', 'end'),
                       type=int, help='Generate csv from start to end')
    return parser


def make_pack(start: int, end: int):
    pack_name = f'pack-{start:03d}-{end:03d}'
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
    number_of_coin_to_get = len(pack_crypto_list)
    # max time beetwen two requests: 60/50 = 1.2
    start_time = time.time()
    number_of_got = 0
    for coin in pack_crypto_list:
        coin_details = cg.get_coin_by_id(coin['id'])
        # TODO: decide what to do with this
        # TODO: compute now or store in a list for later
        number_of_got += 1
        min_expected_time = number_of_got * 1.2
        elapsed_time = time.time() - start_time
        print(f'{number_of_got}) Got coin {coin["id"]}, time {elapsed_time}/{min_expected_time}')
        if elapsed_time < min_expected_time:
            print('too fast, pausing!')
            time.sleep(min_expected_time - elapsed_time)

    # TODO: finish function


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
    known_json = Path('packs', 'known.json').open('w')
    json.dump(known_list, known_json)
    known_json.close()


def generate_csv(start: int, end: int):
    print(f'start csv {start}, end {end}')


def main():
    parser = get_parser()
    args = parser.parse_args()
    if args.make:
        make_pack(args.make[0], args.make[1])
    elif args.known_json:
        generate_known_json()
    elif args.csv:
        generate_csv(args.csv[0], args.csv[1])
    else:
        parser.error(message='No valid argument provided')


if __name__ == '__main__':
    main()

