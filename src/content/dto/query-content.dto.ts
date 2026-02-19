import { ContentType } from '@prisma/client';
import { IsEnum, IsOptional, IsString } from 'class-validator';

export class QueryContentDto {
  @IsOptional()
  @IsEnum(ContentType)
  type?: ContentType;

  @IsOptional()
  @IsString()
  section?: string;

  @IsOptional()
  @IsString()
  search?: string;
}
