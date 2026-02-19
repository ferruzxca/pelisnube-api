import { PlanCode } from '@prisma/client';
import { IsEnum, IsInt, IsString, Length, Max, Min, MinLength } from 'class-validator';

export class CheckoutDto {
  @IsEnum(PlanCode)
  planCode!: PlanCode;

  @IsString()
  @MinLength(12)
  cardNumber!: string;

  @IsString()
  @MinLength(3)
  cardHolder!: string;

  @IsInt()
  @Min(1)
  @Max(12)
  expiryMonth!: number;

  @IsInt()
  @Min(2025)
  @Max(2100)
  expiryYear!: number;

  @IsString()
  @Length(3, 4)
  cvv!: string;
}
