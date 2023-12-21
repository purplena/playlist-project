import React from "react";
import { Box, Stack } from "@mui/material";
import NavbarClient from "../components/Layout/Navbar";
import { Outlet } from "react-router-dom";
import { useMe } from "../hooks/useMe";

export default function Root() {
    useMe();

    return (
        <>
            <NavbarClient />

            <Box
                paddingLeft={2}
                paddingRight={2}
                mt={6}
                id="main-container"
                maxWidth={500}
                ml={"auto"}
                mr={"auto"}
            >
                <Outlet />
            </Box>
        </>
    );
}