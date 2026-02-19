import { IsString, MinLength } from 'class-validator';

export class SendPromotionDto {
  @IsString()
  @MinLength(4)
  subject!: string;

  @IsString()
  @MinLength(20)
  htmlBody!: string;
}
