import { IsArray, IsBoolean, IsInt, IsOptional, IsString, Min } from 'class-validator';

export class UpsertSectionDto {
  @IsOptional()
  @IsString()
  id?: string;

  @IsString()
  key!: string;

  @IsString()
  name!: string;

  @IsString()
  description!: string;

  @IsBoolean()
  isHomeVisible!: boolean;

  @IsInt()
  @Min(0)
  sortOrder!: number;

  @IsOptional()
  @IsArray()
  @IsString({ each: true })
  contentIds?: string[];
}
