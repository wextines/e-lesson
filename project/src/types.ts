export interface TenantData {
  tenant_id: number;
  name: string;
  slug: string;
  logo: string | null;
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  email: string;
  phone: string;
  default_language: string;
}

export interface ApiCourse {
  course_id: number;
  subject: string;
  title: string;
  description: string;
  price: number;
  classLevel: string;
}

export interface LandingApiResponse {
  success: boolean;
  tenant: TenantData;
  courses: ApiCourse[];
  error?: string;
}
