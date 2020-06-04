<?php

namespace Net\Bluewalk\LidlPlus;

class LidlPlus
{
  private $account_url = 'https://accounts.lidl.com/';
  private $appgateway_url = 'https://appgateway.lidlplus.com/app/v19/NL/';
  private $token;
  private $refresh_token;
  private $token_file;

  private static $ENDPOINT_TOKEN = 'connect/token';
  private static $ENDPOINT_RECEIPTS = 'tickets/list/%s';
  private static $ENDPOINT_RECEIPT = 'tickets/%s';
  private static $ENDPOINT_STORE = 'stores/%s';

  public function __construct(string $refresh_token)
  {
    $this->refresh_token = $refresh_token;
    $this->token_file = join(
      DIRECTORY_SEPARATOR,
      [sys_get_temp_dir(), 'lidl-token_' . base64_encode($refresh_token) . '.json']
    );

    if (file_exists($this->token_file))
      $this->token = json_decode(file_get_contents($this->token_file));
  }

  private function _checkAuth()
  {
    if (!$this->token || $this->token->expires < time())
      $this->RefreshToken();
  }

  private function _request(string $endpoint, string $method = 'GET', $data = null)
  {
    $ch = curl_init();

    $headers = [
      'App-Version: 14.21.2',
      'Operating-System: iOS',
      'App: com.lidl.eci.lidl.plus',
      'Accept-Language: nl_NL'
    ];
    $query_params = '';

    if ($this->token)
      $headers[] = 'Authorization: Bearer ' . $this->token->access_token;

    if ($method == 'POST' || $method == 'PUT') {
      if ($data) {
        $data_str = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_str);

        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($data_str);
      }
    }
    if ($method == 'GET')
      if ($data)
        $query_params = '?' . http_build_query($data);

    curl_setopt($ch, CURLOPT_URL, $this->appgateway_url . $endpoint . $query_params);

    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    //curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:1080');

    $result = curl_exec($ch);

    $error = curl_error($ch);
    if ($error)
      throw new Exception('Lidl API: ' . $error);

    curl_close($ch);

    return json_decode($result);
  }

  private function _request_auth()
  {
    $ch = curl_init();

    $request = 'refresh_token=' . $this->refresh_token . '&grant_type=refresh_token';

    curl_setopt($ch, CURLOPT_URL, $this->account_url . $this::$ENDPOINT_TOKEN);

    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Basic ' . base64_encode('LidlPlusNativeClient:secret'),
      'Content-Type: application/x-www-form-urlencoded',
      'Content-Length: ' . strlen($request)
    ]);
    
    //curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:1080');

    $result = curl_exec($ch);

    $error = curl_error($ch);
    if ($error)
      throw new Exception('Lidl API: ' . $error);

    curl_close($ch);

    return json_decode($result);
  }

  public function RefreshToken()
  {
    $result = $this->_request_auth();
    $result->expires = strtotime('+ ' . round($result->expires_in) . ' seconds');

    file_put_contents($this->token_file, json_encode($result));

    $this->token = $result;
  }

  public function GetReceipts(int $page = 1)
  {
    $this->_checkAuth();

    return $this->_request(sprintf($this::$ENDPOINT_RECEIPTS, $page));
  }

  public function GetReceipt(string $id = '')
  {
    $this->_checkAuth();

    return $this->_request(sprintf($this::$ENDPOINT_RECEIPT, $id));
  }

  public function GetStore(string $store)
  {
    return $this->_request(sprintf($this::$ENDPOINT_STORE, $store));
  }

  public function GetReceiptJpeg(string $id = '')
  {
    function strcenter($string)
    {
      return str_pad($string, 50, ' ', STR_PAD_BOTH);
    }

    $receipt = $this->GetReceipt($id);

    if (property_exists($receipt, 'Message')) return null;

    $store = $this->GetStore($receipt->storeCode);

    $header = strcenter($store->address) . PHP_EOL;
    $header .= strcenter($store->postalCode . ' ' . $store->locality) . PHP_EOL;

    $str = sprintf("%-45s%5s\n", "OMSCHRIJVING", "EUR");
    foreach ($receipt->itemsLine as $item) {
      $str .= sprintf("%-45s%5s\n", $item->description, $item->originalAmount);
      if ($item->quantity != 1)
        if ($item->isWeight)
          $str .= "    " . $item->quantity . ' kg x ' . $item->currentUnitPrice . ' EUR/kg' . PHP_EOL;
        else
          $str .= "    " . $item->quantity . ' X ' . $item->currentUnitPrice . PHP_EOL;
  
      if ($item->deposit) {
        $str .= sprintf("%-45s%5s\n", $item->deposit->description, $item->deposit->amount);
        $str .= "    " . $item->deposit->quantity . ' X ' . $item->deposit->unitPrice . PHP_EOL;
      }

      if ($item->discounts)
        foreach ($item->discounts as $discount)
          $str .= sprintf("   %-42s%5s\n", $discount->description, '-' . $discount->amount);
    }
  
    $str .= sprintf("%-38s%s", "", "------------") . PHP_EOL;
    $str .= sprintf("%s%-20s%-25s%5s", "", "Te betalen", $receipt->linesScannedCount . ' art.', $receipt->totalAmount) . PHP_EOL;
    $str .= sprintf("%-38s%s", "", "============") . PHP_EOL;
  
    $str .= sprintf("%-45s%5s\n", $receipt->payments[0]->description, $receipt->payments[0]->amount) . PHP_EOL;
  
    $str .= '--------------------------------------------------' . PHP_EOL . PHP_EOL;
    $str .= trim(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $receipt->payments[0]->rawPaymentInformationHTML))) . PHP_EOL . PHP_EOL;
    $str .= '%              Bedr.Excl         BTW     Bedr.Incl' . PHP_EOL;
  
    foreach ($receipt->taxes as $tax)
      $str .= sprintf("%-9s%15s%12s%14s\n", (int) $tax->percentage, $tax->netAmount, $tax->amount, $tax->taxableAmount);
  
    $str .= '--------------------------------------------------' . PHP_EOL;
    $str .= sprintf("%-9s%15s%12s%14s\n", 'Som', $receipt->totalTaxes->totalNetAmount, $receipt->totalTaxes->totalAmount, $receipt->totalTaxes->totalTaxableAmount);
  
    $footer = sprintf("%-9s%10s%17s%14s\n", substr($receipt->storeCode, 2), $receipt->sequenceNumber . '/' . $receipt->workstation, date('d.m.y', strtotime($receipt->date)), date('H:i', strtotime($receipt->date)));
  
    // 200x71
    $logo = imagecreatefromstring(base64_decode("iVBORw0KGgoAAAANSUhEUgAAAMgAAABPCAMAAACZM3rMAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAMAUExURUpKSktLS0xMTE1NTU5OTk9PT1BQUFFRUVJSUlNTU1RUVFVVVVZWVldXV1hYWFlZWVpaWltbW1" .
      "xcXF1dXV5eXl9fX2BgYGFhYWJiYmNjY2RkZGVlZWZmZmdnZ2hoaGlpaWpqamtra2xsbG1tbW5ubm9vb3BwcHFxcXJycnNzc3R0dHV1dXZ2dnd3d3h4eHl5eXp6ent7e3x8fH19fX5+fn9/f4CAgIGBgYKCgoODg4SEhIWF" .
      "hYaGhoeHh4iIiImJiYqKiouLi4yMjI2NjY6Ojo+Pj5CQkJGRkZKSkpSUlJaWlpeXl5iYmJmZmZqampubm5ycnJ2dnZ6enp+fn6CgoKGhoaKioqOjo6SkpKWlpaampqenp6ioqKmpqaqqqqurq6ysrK2tra6urq+vr7CwsL" .
      "GxsbKysrOzs7S0tLW1tba2tre3t7i4uLm5ubq6uru7u7y8vL29vb6+vr+/v8DAwMHBwcLCwsPDw8TExMXFxcbGxsfHx8jIyMnJycrKysvLy8zMzM3Nzc7Ozs/Pz9DQ0NHR0dLS0tPT09TU1NXV1dbW1tfX19jY2NnZ2dra" .
      "2tvb29zc3N3d3d7e3t/f3+Dg4OHh4eLi4uPj4+Tk5OXl5ebm5ufn5+jo6Onp6erq6uvr6+zs7O3t7e7u7u/v7/Dw8PHx8fLy8vPz8/T09PX19fb29vf39/j4+Pn5+fr6+vv7+/z8/P39/f7+/v///wAAAAAAAAAAAAAAAA" .
      "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA" .
      "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAKXYgk4AAAAJcEhZcwAADsMAAA7DAcdvqGQAAAjUSURBVG" .
      "hD1Zr5XxNHH4AzuwlnuCEERU6lgA0UwhHk7avYisqLLfaFvhYo2lIVEHhblJeKCHIpCEXk8FVQFJAUcpA9/sHO7k4g2Z0lG5Pufnh+29m5nszOzHcnq2NDh953/LmzY991UShBC0IXYaiNx611leXnGvpXHTRKVJ+QRTxr" .
      "HSUZiTGREVHGtLyGpw4GpatNqCKe4RqTQYcACUXt7zQyCVFk90FpFLLgIU/ceKWNSWgijsECPVJAAFOjNmMSkgi1Ui3ygCYnbrvRbVUJSWSnJwJ13wdDwWst1q5QROjZIoB670tsmxNlUJNQRHb7MQMCJ7xlG2VQk1BElh" .
      "pI1Hd/TDP7KIeKhCIyYcU9WTpdYo8D5VCRUESGz+BF4n/4E+VQkVBEhnLxInEtdpRDRUIakQKZEWk9ZiMyWUmgrvuT1HvM5shyI37VMj/3oBwqEorIzr1o1HU/SMsHDaKtUESY+bO4SWK8edx2dnb7NmZIDKdXj1usxTIr" .
      "xZLol0j/0YVuq0pIIqx74owo3CIzmnaO3/sIfLOaKvAzIUz/eX8c3xBZxvX8fCySgJDFPdsaHaRAEWr0m0sB+HYaZZey/3rggtlAcBhLf5qXPFf0QCOqRI76q003BxZ2aZ+Syx3onixX74n2Kiji6cyMDUB2P8qOY295rP" .
      "f7xobrtx7ObUmP6OjrZlSJHMb4xPQcy/lfFg4XickKdE+WlMuiF2pO5GYyejJkMfWg7HiYvbWll682sQeN1L/iUSVHE5FzsfeVt4YnZ1GqLFF1f4eIAH6SKxWBC8WJy49RtKmpCLWyittAlIvodNHlD+38z6GliHO2xjaC" .
      "Cd+DEdHpc7t5k08UuZUCOFAWEdwdIj2gCP2iNoKsmJC+rFONCfKVSyCyprkqxi1cGblC3K1ojAj137K8vLwc/LCApNy8vHzrEMouh3up2gB/0NJhSbxI3yyBtZ+KV6iiL1mBM372EiyTm4F/3YnNhDeLWkS/GRRhPU6Hw7" .
      "HejG0q6ruP8KYzwAsGNV3Dn2STn9+XjMk+V/vK19iTIwzG+m1o74JltgeNKM0PYJvneiRuhxPh2b6BF/leQQhIP6+KFLIbioawi/D6FaUiIGEMteh8FIfS/ADnloX7/oRDhJ68cBCmRJXfx42echEdeem9UEZ1EfphtU+4" .
      "FVXYJ5qGHDIiuCZBCj/f1Rehxyr9GowsGpLME7yI3pxnPviT6BB9xxZfRmWR/ccVMSgvQm8ZlKxdWJGEa4O/3bCg2XUIYV3hy6grQk/aRB4wbLI8Ep8HrV3AiJh+dTE79wvFgwJSp/kFQ1UR17wN89pOlo7uoQwCjpECzM" .
      "kRFIE1/HQKXR6gH9jlCqkp4n5aI3kyOECpN/zjcU5YcXsbL8KuNIrvka1v+FLqiXgWuf0cB1k2eFjOPW3DnuQJIp5+nzWPh7i6wBVTT8T5VNjPceg/E54PLtuQFZ9NEGHH89G1F6J2ii/394sIbx7UuO2IXY4s6hV2Rs9I" .
      "ieTMSACJzFnRtRei4gmXHowI8ykijPsjZ0KNW4/wgNFK/m/c4kMNlsplQyKL59C1FzjDuHTlIgy14w5ahHEsNLfANPpRpd+nAlIMhXfdrOd/xfxygKtcToQoG+PSlYownvVb1xaDFdlfbC7KrHOw9LBVsn+IiczudT7gNz" .
      "wQbcIsW0hk9gt07YWonuDSFYrQ77qt5tLZ4ESYlZ6LGURcnYMar8W24o++uKOU0wXm+grMfEciYzno2gtxYYZLVyTCbI01njGQlqBEqDf9dRlw4sbV2UcCjwcHkcgNBJHZPnMZv7PDWp3d4uWX/HaJa06JyNbE9bNwyIlg" .
      "RKiPky1Z/O8a9+VYRYD54Qs42byOjbUEkRdXxA2TP29wDQYW2XvZKQTeQYjQmxOt+ejxiM4/K7Og4iCyOjcYeZEPzWZ0eUDMI77NACJw1bljMwqdVizCON/0Wg46D/D/uOEBqW3wRQkrkta9tbPRl4uuDgBZ83yPjhbxbE" .
      "2cj/V2WamIY62vKka07IDkNCWPF0i/yx3PY0WM/2z7d1mKZDkjL6/xPTpKxLM5fiXx8LFQJuKx/14dK24OxLf+iluJRBBJd/jdEyuiI/QkIW01ckAIneVFaOdCUzLpU1KJSIt9sipV0mMQ3/7W/cQWaKqAxN5NPp7Bi2AB" .
      "p1eFEEhW5MXSjewov94qEImoupQTLRl9kNjxmmIdUzVHmwBTFzrZDkIkon2HLyIrUvhVSZKoXSTC0JAtvAiIEU8OCEi6+57roHvOdtTTRaT02Bmh9jXcPoLF8NkqzTJcIccwVkQXFSP5+QQReulBX19fZzVKDAyI/2FD+K" .
      "FdY0cEwCDrxw/QY+4+rL3j80BPIQKYuE+LPozCMl0NijcrQcTTZ8nKyjqZgBIDE13yRniKocmzarnWiJN3uA8H6PZiWPsJtOAHJPY6V2j2IixzKk36LMiARAKfxvsDYy3kAff7xxXYzx90ILuVP2gL6jReF1u7yP0HGfg0" .
      "3p8wiLDuoRLcmID0tnV+2IIRAUbbKP/MaiECA/ovpAEkyBTGIygRval+RvhPWBMRlh6yiscEmO68RdNIsQhpPN3mnXvaiLCeJ//wPxQCafe2UJeUiQBDTNKZpj9c3kKfKNJpjgyK1Hp/EdYzW+kbR4KEXj4u4aGb0lAxWa" .
      "KikwuvDmw4Dz81mChDtxQSUz4HRZi3M5NB8WxZ/HXD/tSXh/sESL936MEy/3+Giskx/fzlu027y7dO+wK6p5CpF7tQhPW4gkR62u6cq/KaEKldm76dCly7W9hdfaD30S2luGlOJBx4ZmqFaIU42aXJ50HhEmE3rvETnshu" .
      "Wxc/eKoQLhFHH3/eTuR2C3G76oRJxD5q4QIjIrudPzfQgPCI2AdKeY+c2xvajEeYRPZGLPz8yOjcRCnqEw4RZqScGw9g7tbig19EOEScP5vh2waRPOCzD6pOWEZk83YyIFL79zT0CM8cod61pZ7qOogTNSEsIiz9fvT3XU" .
      "09wiTCUnvCp29awbJ/AUCewlrlEY2aAAAAAElFTkSuQmCC"));
  
    // Replace path by your own font path
    $font = __DIR__ . '/receipt_font.ttf';
  
    list(, $text_height, $text_width) = imageftbbox(12, 0, $font, $str);
  
    $height = $text_height + 40 + 181 /* logo */ + 150 /* barcode */;
    $width = $text_width + 40;
    $pos = 0;
  
    // Create the image
    $im = imagecreatetruecolor($width, $height);
  
    // Create a few colors
    $white = imagecolorallocate($im, 255, 255, 255);
    $grey = imagecolorallocate($im, 30, 30, 30);
    $black = imagecolorallocate($im, 0, 0, 0);
  
    // Create white background
    imagefilledrectangle($im, 0, 0, $width, $height, $white);
  
    // Add logo
    imagecopy($im, $logo, $width / 2 - 100, 10, 0, 0, 200, 79);
    $pos += 110;
  
    // And add header
    imagettftext($im, 12, 0, 20, $pos, $grey, $font, $header);
    $pos += 90;

    // And add the text
    imagettftext($im, 12, 0, 20, $pos, $grey, $font, $str);
    $pos += $text_height + 20;
  
    // Add barcode
    $generator = new barcode_generator();
    $barcode = $generator->render_image('itf', $receipt->barCode, ['f' => 'png', 'w' => $width]);
    imagecopy($im, $barcode, 0, $pos, 0, 0, $width, 80);
    $pos += 80 + 30;
  
    // Final line
    imagettftext($im, 12, 0, 20, $pos, $grey, $font, $footer);
  
    // Using imagepng() results in clearer text compared with imagejpeg()
    ob_start();
    imagepng($im);
    $data = ob_get_contents();
    ob_end_clean();
    imagedestroy($im);
  
    return $data;
  }
}