import sys, os, json, time, hmac, hashlib, base64
from datetime import datetime
import urllib.request

def get_ocr_result(img_path):
    # 配置参数
    secret_id = os.environ.get('SecretId')
    secret_key = os.environ.get('SecretKey')
    if not secret_id or not secret_key:
        return "Error: SecretId or SecretKey not set."

    try:
        with open(img_path, 'rb') as f:
            img_data = f.read()
    except Exception as e:
        return f"Error reading image: {e}"

    # 接口参数
    base64_img = base64.b64encode(img_data).decode('utf-8')
    service, host, region, action, version = "ocr", "ocr.tencentcloudapi.com", "ap-guangzhou", "GeneralBasicOCR", "2018-11-19"
    algorithm, timestamp = "TC3-HMAC-SHA256", int(time.time())
    date = datetime.utcfromtimestamp(timestamp).strftime('%Y-%m-%d')
    
    payload = json.dumps({"ImageBase64": base64_img})
    ct = "application/json; charset=utf-8"
    canonical_headers = f"content-type:{ct}\nhost:{host}\n"
    signed_headers = "content-type;host"
    hashed_payload = hashlib.sha256(payload.encode('utf-8')).hexdigest()
    canonical_request = f"POST\n/\n\n{canonical_headers}\n{signed_headers}\n{hashed_payload}"

    credential_scope = f"{date}/{service}/tc3_request"
    hashed_canonical_request = hashlib.sha256(canonical_request.encode('utf-8')).hexdigest()
    string_to_sign = f"{algorithm}\n{timestamp}\n{credential_scope}\n{hashed_canonical_request}"

    # 计算签名
    def sign(key, msg): return hmac.new(key, msg.encode('utf-8'), hashlib.sha256).digest()
    secret_date = sign(("TC3" + secret_key).encode('utf-8'), date)
    secret_service = sign(secret_date, service)
    secret_signing = sign(secret_service, "tc3_request")
    signature = hmac.new(secret_signing, string_to_sign.encode('utf-8'), hashlib.sha256).hexdigest()

    # 拼接 Authorization
    authorization = f"{algorithm} Credential={secret_id}/{credential_scope}, SignedHeaders={signed_headers}, Signature={signature}"
    headers = {
        "Authorization": authorization, "Content-Type": ct, "Host": host,
        "X-TC-Action": action, "X-TC-Version": version,
        "X-TC-Timestamp": str(timestamp), "X-TC-Region": region,
    }

    # 发送请求
    req = urllib.request.Request(f"https://{host}", data=payload.encode('utf-8'), headers=headers, method="POST")
    try:
        with urllib.request.urlopen(req) as response:
            res = json.loads(response.read().decode('utf-8'))['Response']
            if 'TextDetections' in res:
                return "\t".join([d['DetectedText'] for d in res['TextDetections']])
            return f"API Error: {res.get('Error', {}).get('Message', 'Unknown error')}"
    except Exception as e: return f"Network Error: {e}"

if __name__ == "__main__":
    if len(sys.argv) > 1:
        print(get_ocr_result(sys.argv[1]), end="")
