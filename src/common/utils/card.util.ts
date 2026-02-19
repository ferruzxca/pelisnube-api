export const getCardLast4 = (cardNumber: string): string => {
  const digits = cardNumber.replace(/\D/g, '');
  return digits.slice(-4).padStart(4, '0');
};

export const getCardBrand = (cardNumber: string): string => {
  const digits = cardNumber.replace(/\D/g, '');

  if (/^4\d{12}(\d{3})?$/.test(digits)) {
    return 'VISA';
  }

  if (/^5[1-5]\d{14}$/.test(digits)) {
    return 'MASTERCARD';
  }

  if (/^3[47]\d{13}$/.test(digits)) {
    return 'AMEX';
  }

  if (/^6(?:011|5\d{2})\d{12}$/.test(digits)) {
    return 'DISCOVER';
  }

  return 'UNKNOWN';
};
