import React from 'react';
import { CircularProgress, Stack } from '@mui/material';
import { Outlet } from 'react-router-dom';
import { useMe } from '../hooks/useMe';

export default function Root() {
  const { isLoading } = useMe();

  return isLoading ? (
    <Stack justifyContent="center" alignItems="center" mt={5}>
      <CircularProgress />
    </Stack>
  ) : (
    <Outlet />
  );
}
