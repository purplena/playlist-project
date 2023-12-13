import React, { useEffect } from "react";
import { Box } from "@mui/material";
import Navbar from "../components/Layout/Navbar";
import { Outlet } from "react-router-dom";
import { useMe } from "../hooks/useMe";

export default function Root() {
    useMe();

    return (
        <>
            <Navbar />
            <Box paddingLeft={2} paddingRight={2} mt={6} id="main-container">
                <Outlet />
            </Box>
        </>
    );
}
