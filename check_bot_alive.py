# 사용법
# linux 용
# bot이 뒤지면 line으로 알람을 보내고, 포지션이 남아있는 경우 비상 탈출을 시도함
#
# 1. config_path 에서 phemex api key 파일 지정
# 2. 118 line 에서 프로세스 이름 확인  
# 3. 132 line 에서 감시할 시간 지정
# 4. bot 실행 시 alive 스크립트도 같이 백그라운드로 실행
#

import json
import ccxt
import psutil
import requests
import time
import datetime

def notify(msg):
    try:
        URL = 'https://notify-api.line.me/api/notify'
        TOKEN = 't34ZhDXqARxLDD8Lrs9Q20cl1gWN3fG3KGqiWHPdQKB'
        
        response = requests.post(
            URL,
            headers={
            'Authorization': 'Bearer ' + TOKEN
            },
            data={
                'message' : msg
            }
        )
        
    except Exception as e:
        logger.exception("While notify...")

def checkAbnormal():
    config_path = "/root/phemex/bot3/php-trading-bot-bx/config/phmexConfig.json"

    with open(config_path, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    apikey = data.get("ProdApiKey")
    secretkey = data.get("ProdSecret")
    
    ex = ccxt.phemex({
        'apiKey': apikey,
        'secret': secretkey,
    })
    
    try:
        positions = ex.fetch_positions(params={'currency':'BTC'})
    
        if len(positions) >= 1 and positions[0].get("contracts") > 0.0:
            orders = ex.fetch_open_orders(symbol="BTCUSD")
            
            isThereConditionalOrder = False
            for order in orders:
                if order.get("info").get("orderType") == "MarketIfTouched":
                    isThereConditionalOrder = True
            
            if isThereConditionalOrder == False:
                # 포지션은 있는데 stop 주문이 없는 경우
                notify("포지션은 있는데 stop 주문이 없음")
                # 비상 탈출 필요
                # emergencyExitPosition()
    except Exception as e:
        notify("checkAbnormal Exception")
        notify(str(e))

def emergencyExitPosition():
    config_path = "/root/phemex/bot3/php-trading-bot-bx/config/phmexConfig.json"

    with open(config_path, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    apikey = data.get("ProdApiKey")
    secretkey = data.get("ProdSecret")
    
    ex = ccxt.phemex({
        'apiKey': apikey,
        'secret': secretkey,
    })
    
    startTime = time.time()
    while True:
        try:
            positions = ex.fetch_positions(params={'currency':'BTC'})
            orderbook = ex.fetch_order_book('BTCUSD')

            notify("현재 포지션 크기 : "+str(positions[0].get("contracts")))
        
            if len(positions) == 1 and positions[0].get("contracts") == 0.0:
                notify("포지션 크기가 0이라서 종료")
                break
        
            for position in positions:
                notify("side : "+str(position.get("side")))
                notify("size : "+str(position.get("contracts")))
                #print("side : "+position.get("side"))
                #print("size : "+str(position.get("contracts")))
        
                if position.get("side") == "short":
                    #print("order price : "+str(orderbook.get("bids")[0]))
                    #print("order price : "+str(orderbook.get("bids")[1]))
                    notify("매수 시도(탈출가격) : "+str(orderbook.get("bids")[0][0]))
                    ex.create_order("BTCUSD", "limit", "buy", position.get("contracts"), orderbook.get("bids")[0][0], {'reduceOnly': True})
                elif position.get("side") == "long":
                    #print("order price : "+str(orderbook.get("asks")[0]))
                    #print("order price : "+str(orderbook.get("asks")[1]))
                    notify("매도 시도(탈출가격) : "+str(orderbook.get("asks")[0][0]))
                    ex.create_order("BTCUSD", "limit", "sell", position.get("contracts"), orderbook.get("asks")[0][0], {'reduceOnly': True})
                else:
                    notify("something is wrong..")
                    notify(str(position))
                    #print("something is wrong...")
                    #print(position)
        
            if (time.time() - startTime) > 300:
                notify("탈출 시도 5분 경과. 탈출 실패. 시장가 탈출 시도")

                orders = ex.fetch_open_orders(symbol="BTCUSD")
                for order in orders:
                    ex.cancel_order(order.get("info").get("orderID"), symbol="BTCUSD")

                positions = ex.fetch_positions(params={'currency':'BTC'})
                
                if len(positions) < 1:
                    break

                if position.get("side") == "short":
                    notify("시장가 매수 시도")
                    ex.create_order("BTCUSD", "market", "buy", position.get("contracts"), None, {'reduceOnly': True})
                elif position.get("side") == "long":
                    notify("시장가 매도 시도")
                    ex.create_order("BTCUSD", "market", "sell", position.get("contracts"), None, {'reduceOnly': True})
            
            time.sleep(1)
            notify("========================")
        except Exception as e:
            notify(str(e))

    # 모든 주문 취소
    try:
        orders = ex.fetch_open_orders(symbol="BTCUSD")
        
        for order in orders:
            ex.cancel_order(order.get("info").get("orderID"), symbol="BTCUSD")
        
    except Exception as e:
        notify("emergencyExitPosition Exception")
        notify(str(e))

while True:
    time.sleep(1)
    p = psutil.process_iter(attrs=["name", "exe", "cmdline"])
    count = 0

    for i in p:
        if "phemex_real_start.php" in i.info['cmdline']:
            count += 1

    if count == 0:
        notify("봇 죽음")
        notify("봇 죽음")
        notify("봇 죽음")

        # 새벽에만 비상탈출 원할 경우 00시 ~ 09시
        #if datetime.datetime.now().hour < 9:

        # 하루종일 감시
        if datetime.datetime.now().hour > 0:
            notify("포지션 비상 종료 시작")
            emergencyExitPosition()

        break

    # 비정상 상황 탐지기
    if int(time.time() % 5) == 10:
        checkAbnormal()
