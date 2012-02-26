<?php
/**
  phpSec - A PHP security library

  @author    Audun Larsen <larsen@xqus.com>
  @copyright Copyright (c) Audun Larsen, 2011, 2012
  @link      https://github.com/phpsec/phpSec
  @license   http://opensource.org/licenses/mit-license.php The MIT License
  @package   phpSec
 */

/**
 * Providees pre shared one-time-password functionality. Experimental.
 */
class phpsecOtpcard {
  const HASH_TYPE = 'sha256';

  /**
   * Create a list of 64 pre shared one-time-passwords,
   * or a so called password card.
   *
   * This differs from phpsecOtp::generate() because passwords generated by
   * this function is saved permanent and can be validated on a later time.
   */
  public static function create($length = 6, $num = 64) {
    $card['list'] = array();
    for($i = 0; $i < $num; $i++) {
      $card['list'][$i]   = phpsecRand::str($length);
      $card['usable'][$i] = true;
    }

    $card = self::hash($card);
    self::save($card);

    return $card['id'];
  }

  /**
   * Validates a pre shared one-time-password.
   *
   * @param string $cardId
   *   Card ID.
   *
   * @param integer $selected
   *   OTP ID the user is expected to use. Usually
   *   provided by phpsecOtp::cardSelect().
   *
   * @param string $otp
   *   The password provided by the user.
   *
   * @return bolean
   */
  public static function validate($cardId, $selected, $otp) {
    $card = self::load($cardId);
    if(isset($card['usable'][$selected]) && $card['usable'][$selected] === true) {
      if($card['list'][$selected] == $otp) {
        unset($card['usable'][$selected]);

        $card = self::hash($card);
        self::save($card);

        return true;
      }
    }
    return false;
  }

  /**
   * Select a pre shared OTP from a list that a user can use.
   *
   * @param string $cardId
   *   Card ID to select a OTP from.
   *
   * @return integer
   *   OTP ID of a available OTP.
   */
  public static function select($cardId) {
    $card = self::load($cardId);

    $available = array_keys($card['usable']);
    $selected  = phpsecRand::int(0, count($available)-1);

    return $available[$selected];
  }
  /**
   * Load a password card.
   *
   * @param string $cardId
   *   Card ID.
   *
   * @return array
   *   A array containing the card data.
   */
  public static function load($cardId) {
    $card = phpsec::$store->read('otp-card', $cardId);
    if($card !== false) {
      if($card['hash'] !== hash(self::HASH_TYPE, $card['list'])) {
        return false;
      }
      $card['list'] = json_decode(base64_decode($card['list']), true);
      return $card;
    }
    return false;
  }

  /**
   * Get the number of unused OTPs on a password card.
   *
   * @param string $cardId
   *   Card ID.
   *
   * @return integer
   *   Number of unused OTPs.
   */
  public static function remaining($cardId) {
    $card = self::load($cardId);

    return count($card['usable']);
  }

  /**
   * Save a password card. Can only be called after phpsecOtp::cardHash().
   *
   * @param array $card
   *   Array containing a already hashed card.
   *
   * @return bolean
   *   Returns true on success and false on error.
   */
  private static function save($card) {
    /* TODO: Encrypt before saving. */
    return phpsec::$store->write('otp-card', $card['id'], $card);
  }

  /**
   * Prepeare the password card for saving.
   * Must be called before phpsecOtp::cardSave().
   *
   * @param array $card
   *   Array containing the card data to hash.
   *
   * @return array
   *   Arrray containing hashed card data. Ready for phpsecOtp::cardSave().
   */
  private static function hash($card) {
    /* We are encoding the password list just because we want to make
     * the file look nice, and to avoid bugs with special characters. */
    $card['list'] = base64_encode(json_encode($card['list']));
    $card['hash'] = hash(self::HASH_TYPE, $card['list']);
    $card['id']   = substr($card['hash'], 0, 12);

    return $card;
  }

}