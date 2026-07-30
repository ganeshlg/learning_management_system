-- Add payment-plan module unlock settings and ordering support
-- Run this once on the LMS database.

ALTER TABLE courses
  ADD COLUMN IF NOT EXISTS single_pay_modules INTEGER DEFAULT 0,
  ADD COLUMN IF NOT EXISTS two_pay_first_modules INTEGER DEFAULT 0,
  ADD COLUMN IF NOT EXISTS two_pay_second_modules INTEGER DEFAULT 0,
  ADD COLUMN IF NOT EXISTS three_pay_first_modules INTEGER DEFAULT 0,
  ADD COLUMN IF NOT EXISTS three_pay_second_modules INTEGER DEFAULT 0,
  ADD COLUMN IF NOT EXISTS three_pay_third_modules INTEGER DEFAULT 0;

ALTER TABLE modules
  ADD COLUMN IF NOT EXISTS module_order INTEGER DEFAULT 0;

ALTER TABLE purchases
  ADD COLUMN IF NOT EXISTS payment_plan VARCHAR(50) DEFAULT 'one_time',
  ADD COLUMN IF NOT EXISTS paid_installments INTEGER DEFAULT 1,
  ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'active',
  ADD COLUMN IF NOT EXISTS purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;